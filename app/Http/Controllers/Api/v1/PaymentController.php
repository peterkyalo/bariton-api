<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateMpesaRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderFulfillmentService;
use App\Services\Payments\MpesaPaymentGateway;
use App\Services\Payments\PayPalPaymentGateway;
use App\Services\Payments\StripePaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        protected MpesaPaymentGateway $mpesaGateway,
        protected StripePaymentGateway $stripeGateway,
        protected PayPalPaymentGateway $paypalGateway,
        protected OrderFulfillmentService $fulfillmentService
    ) {}

    /**
     * Initiate M-Pesa STK Push payment for an order.
     */
    public function initiateMpesa(InitiateMpesaRequest $request, string $orderUuid): JsonResponse
    {
        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($order->isPaid()) {
            return response()->json(['message' => 'This order is already paid in full.'], 400);
        }

        if ($order->isExpired()) {
            $order->update(['status' => 'expired']);

            return response()->json(['message' => 'This order has expired. Please initiate a new order.'], 400);
        }

        $phone = $request->input('phone');
        $result = $this->mpesaGateway->initiate($order, ['phone' => $phone]);

        if (! $result->success) {
            return response()->json([
                'message' => $result->message,
            ], 422);
        }

        // Record pending payment
        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'gateway' => 'mpesa',
            'amount_kes' => $order->total_kes,
            'status' => 'pending',
            'checkout_request_id' => $result->data['checkout_request_id'] ?? null,
            'merchant_request_id' => $result->data['merchant_request_id'] ?? null,
            'payer_phone' => $result->data['payer_phone'] ?? null,
            'payload' => $result->data,
        ]);

        return response()->json([
            'message' => $result->message,
            'payment' => new PaymentResource($payment),
            'checkout_request_id' => $payment->checkout_request_id,
        ]);
    }

    /**
     * Initiate Stripe card payment with 3D Secure and idempotency key.
     */
    public function initiateStripe(Request $request, string $orderUuid): JsonResponse
    {
        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($order->isPaid()) {
            return response()->json(['message' => 'This order is already paid in full.'], 400);
        }

        if ($order->isExpired()) {
            $order->update(['status' => 'expired']);

            return response()->json(['message' => 'This order has expired. Please initiate a new order.'], 400);
        }

        $idempotencyKey = 'stripe_ord_'.$order->id.'_'.(string) Str::uuid();
        $result = $this->stripeGateway->initiate($order, ['idempotency_key' => $idempotencyKey]);

        if (! $result->success) {
            return response()->json(['message' => $result->message], 422);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'gateway' => 'stripe',
            'amount_kes' => $order->total_kes,
            'status' => 'pending',
            'reference' => $result->reference,
            'stripe_payment_intent_id' => $result->data['payment_intent_id'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'payload' => $result->data,
        ]);

        return response()->json([
            'message' => 'Stripe PaymentIntent initialized.',
            'client_secret' => $result->data['client_secret'] ?? null,
            'payment_intent_id' => $payment->stripe_payment_intent_id,
            'payment' => new PaymentResource($payment),
        ]);
    }

    /**
     * Initiate PayPal payment in USD at the locked exchange rate.
     */
    public function initiatePaypal(Request $request, string $orderUuid): JsonResponse
    {
        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($order->isPaid()) {
            return response()->json(['message' => 'This order is already paid in full.'], 400);
        }

        if ($order->isExpired()) {
            $order->update(['status' => 'expired']);

            return response()->json(['message' => 'This order has expired. Please initiate a new order.'], 400);
        }

        $result = $this->paypalGateway->initiate($order);

        if (! $result->success) {
            return response()->json(['message' => $result->message], 422);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'gateway' => 'paypal',
            'amount_kes' => $order->total_kes,
            'status' => 'pending',
            'reference' => $result->reference,
            'paypal_order_id' => $result->data['paypal_order_id'] ?? null,
            'payload' => $result->data,
        ]);

        return response()->json([
            'message' => 'PayPal order initialized.',
            'paypal_order_id' => $payment->paypal_order_id,
            'approve_link' => $result->data['approve_link'] ?? null,
            'amount_usd' => $result->data['amount_usd'] ?? null,
            'payment' => new PaymentResource($payment),
        ]);
    }

    /**
     * Capture PayPal payment after approval on frontend.
     */
    public function capturePaypal(Request $request, string $orderUuid): JsonResponse
    {
        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('gateway', 'paypal')
            ->latest()
            ->firstOrFail();

        if ($payment->isSuccessful()) {
            return response()->json([
                'message' => 'Payment already completed.',
                'order_status' => 'paid',
            ]);
        }

        $payload = $request->all();
        $confirmResult = $this->paypalGateway->confirm($payment, $payload);

        if ($confirmResult->success) {
            $payment->update([
                'reference' => $confirmResult->reference,
                'payload' => array_merge($payment->payload ?? [], $payload),
            ]);

            $this->fulfillmentService->fulfill($order, $payment);

            return response()->json([
                'message' => 'PayPal payment captured successfully and access granted.',
                'order_status' => 'paid',
            ]);
        }

        return response()->json(['message' => 'Could not verify PayPal payment capture.'], 400);
    }

    /**
     * Stripe Webhook: verifies Stripe-Signature and marks order paid only on payment_intent.succeeded.
     */
    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        // Verify webhook signature if configured and signature header present
        if (! empty($webhookSecret) && $webhookSecret !== 'whsec_mock' && $sigHeader) {
            $valid = $this->verifyStripeSignature($payload, $sigHeader, $webhookSecret);
            if (! $valid) {
                Log::warning('Stripe webhook signature verification failed.');

                return response()->json(['error' => 'Invalid webhook signature.'], 400);
            }
        }

        $data = json_decode($payload, true) ?: $request->all();
        $eventType = $data['type'] ?? '';

        Log::info("Stripe Webhook received: {$eventType}");

        if ($eventType === 'payment_intent.succeeded') {
            $intent = $data['data']['object'];
            $intentId = $intent['id'];

            $payment = Payment::where('stripe_payment_intent_id', $intentId)
                ->orWhere('reference', $intentId)
                ->first();

            if ($payment) {
                // Idempotency check: if already successful, acknowledge immediately
                if ($payment->isSuccessful()) {
                    return response()->json(['received' => true, 'already_processed' => true]);
                }

                $confirmResult = $this->stripeGateway->confirm($payment, $data);
                if ($confirmResult->success) {
                    $payment->update([
                        'reference' => $intentId,
                        'payload' => $data,
                    ]);

                    // Fulfill order, grant module access, issue sequential receipt
                    $this->fulfillmentService->fulfill($payment->order, $payment);
                }
            } else {
                Log::warning("Stripe PaymentIntent {$intentId} not found in database.");
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * PayPal Webhook: handles PAYMENT.CAPTURE.COMPLETED.
     */
    public function paypalWebhook(Request $request): JsonResponse
    {
        $data = $request->all();
        $eventType = $data['event_type'] ?? '';

        Log::info("PayPal Webhook received: {$eventType}");

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource = $data['resource'] ?? [];
            $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
            $captureId = $resource['id'] ?? null;

            $payment = Payment::where(function ($q) use ($orderId, $captureId) {
                if ($orderId) {
                    $q->where('paypal_order_id', $orderId);
                }
                if ($captureId) {
                    $q->orWhere('reference', $captureId);
                }
            })->first();

            if ($payment && ! $payment->isSuccessful()) {
                $confirmResult = $this->paypalGateway->confirm($payment, $data);
                if ($confirmResult->success) {
                    $payment->update([
                        'reference' => $captureId ?: $payment->reference,
                        'payload' => $data,
                    ]);

                    $this->fulfillmentService->fulfill($payment->order, $payment);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * Incoming M-Pesa Daraja STK Push Callback.
     * Uses secret URL token and idempotent processing.
     */
    public function mpesaCallback(Request $request, string $token): JsonResponse
    {
        $expectedToken = config('services.mpesa.callback_token', 'bmas-mpesa-secret-callback-token');
        if ($token !== $expectedToken) {
            Log::warning('Unauthorized M-Pesa callback attempted with invalid token.');
            abort(401, 'Unauthorized callback token.');
        }

        $payload = $request->all();
        Log::info('M-Pesa STK Callback received', ['payload' => $payload]);

        $stkCallback = $payload['Body']['stkCallback'] ?? $payload;
        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;

        if (! $checkoutRequestId) {
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Missing CheckoutRequestID'], 400);
        }

        $payment = Payment::where('checkout_request_id', $checkoutRequestId)->first();
        if (! $payment) {
            Log::warning("M-Pesa callback for unknown CheckoutRequestID: {$checkoutRequestId}");

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Acknowledged']);
        }

        // Idempotency: if already successful, acknowledge immediately
        if ($payment->isSuccessful()) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }

        $order = $payment->order;
        $confirmResult = $this->mpesaGateway->confirm($payment, $payload);

        if ($confirmResult->success) {
            $payment->update([
                'reference' => $confirmResult->reference,
                'payload' => $payload,
            ]);

            // Fulfill and issue sequential receipt
            $this->fulfillmentService->fulfill($order, $payment);
        } else {
            $payment->update([
                'status' => 'failed',
                'payload' => $payload,
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * Polling endpoint to check payment and fulfillment status.
     */
    public function checkStatus(Request $request, string $orderUuid): JsonResponse
    {
        $order = Order::where('uuid', $orderUuid)
            ->with(['payments', 'successfulPayment', 'receipt', 'items'])
            ->firstOrFail();

        if ($order->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['admin', 'finance_manager', 'finance_staff'])) {
            abort(403);
        }

        $latestPayment = $order->payments->last();

        return response()->json([
            'order_status' => $order->status,
            'is_paid' => $order->isPaid(),
            'is_expired' => $order->isExpired(),
            'payment' => $latestPayment ? new PaymentResource($latestPayment) : null,
            'receipt' => $order->receipt ? [
                'receipt_number' => $order->receipt->receipt_number,
                'download_url' => url("/api/v1/student/receipts/{$order->receipt->uuid}/download"),
            ] : null,
        ]);
    }

    /**
     * Verify Stripe webhook HMAC SHA256 signature.
     */
    protected function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool
    {
        $items = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($items as $item) {
            $parts = explode('=', trim($item), 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        // Check tolerance: within 5 minutes
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }
}
