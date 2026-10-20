<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateMpesaRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderFulfillmentService;
use App\Services\Payments\MpesaPaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected MpesaPaymentGateway $mpesaGateway,
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
}
