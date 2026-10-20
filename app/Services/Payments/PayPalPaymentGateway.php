<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayPalPaymentGateway implements PaymentGatewayInterface
{
    protected string $mode;
    protected string $clientId;
    protected string $clientSecret;
    protected string $webhookId;

    public function __construct()
    {
        $this->mode = (string) config('services.paypal.mode', 'sandbox');
        $this->clientId = (string) config('services.paypal.client_id', '');
        $this->clientSecret = (string) config('services.paypal.client_secret', '');
        $this->webhookId = (string) config('services.paypal.webhook_id', '');
    }

    public function getBaseUrl(): string
    {
        return $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function getAccessToken(): ?string
    {
        if (empty($this->clientId) || empty($this->clientSecret) || $this->clientId === 'mock_paypal_client_id') {
            return 'mock_paypal_access_token';
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->timeout(10)
                ->post($this->getBaseUrl().'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }
        } catch (\Exception $e) {
            Log::warning('PayPal token exception: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Initiate PayPal order in USD at the exchange rate locked on the order.
     */
    public function initiate(Order $order, array $params = []): PaymentResult
    {
        $usdAmount = number_format($order->total_usd_cents / 100, 2, '.', '');
        $token = $this->getAccessToken();

        if ($token && $this->clientId !== 'mock_paypal_client_id') {
            try {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->post($this->getBaseUrl().'/v2/checkout/orders', [
                        'intent' => 'CAPTURE',
                        'purchase_units' => [
                            [
                                'reference_id' => $order->order_number,
                                'description' => "Bariton Academy Order {$order->order_number}",
                                'amount' => [
                                    'currency_code' => 'USD',
                                    'value' => $usdAmount,
                                ],
                            ],
                        ],
                    ]);

                $json = $response->json();
                if ($response->successful() && ! empty($json['id'])) {
                    $orderId = $json['id'];
                    $approveLink = null;
                    foreach ($json['links'] ?? [] as $link) {
                        if (($link['rel'] ?? '') === 'approve') {
                            $approveLink = $link['href'];
                            break;
                        }
                    }

                    return PaymentResult::pending(
                        reference: $orderId,
                        message: 'PayPal order created.',
                        data: [
                            'paypal_order_id' => $orderId,
                            'approve_link' => $approveLink,
                            'amount_usd' => $usdAmount,
                        ]
                    );
                }

                return PaymentResult::failed($json['message'] ?? 'Failed to initialize PayPal order.', $json);
            } catch (\Exception $e) {
                Log::error('PayPal initiate exception: '.$e->getMessage());
                return PaymentResult::failed('Could not connect to PayPal API: '.$e->getMessage());
            }
        }

        // Test mode simulation
        $mockOrderId = 'PAYPAL_ORDER_'.strtoupper(Str::random(12));
        return PaymentResult::pending(
            reference: $mockOrderId,
            message: 'PayPal order created (test mode).',
            data: [
                'paypal_order_id' => $mockOrderId,
                'approve_link' => 'https://sandbox.paypal.com/checkoutnow?token='.$mockOrderId,
                'amount_usd' => $usdAmount,
            ]
        );
    }

    /**
     * Confirm captured payment from PayPal webhook or capture call.
     */
    public function confirm(Payment $payment, array $payload = []): PaymentResult
    {
        $status = $payload['status'] ?? $payload['resource']['status'] ?? null;
        $captureId = $payload['resource']['id'] ?? $payload['id'] ?? null;

        if ($status === 'COMPLETED') {
            $reference = $captureId ?: 'PAYPAL-CAP-'.strtoupper(Str::random(10));
            return PaymentResult::successful($reference, 'Payment confirmed via PayPal.', [
                'paypal_payload' => $payload,
                'capture_id' => $reference,
            ]);
        }

        return PaymentResult::failed('PayPal payment is not completed.', ['payload' => $payload]);
    }

    /**
     * Query PayPal order status.
     */
    public function status(Payment $payment): PaymentResult
    {
        $orderId = $payment->paypal_order_id ?: $payment->reference;

        if (empty($orderId)) {
            return PaymentResult::failed('Payment does not have a PayPal Order ID.');
        }

        $token = $this->getAccessToken();
        if ($token && $this->clientId !== 'mock_paypal_client_id') {
            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->get($this->getBaseUrl()."/v2/checkout/orders/{$orderId}");

                $json = $response->json();
                if ($response->successful()) {
                    if (($json['status'] ?? '') === 'COMPLETED') {
                        return PaymentResult::successful($orderId, 'Payment is completed on PayPal.', $json);
                    }
                    return PaymentResult::pending($orderId, 'Payment is awaiting PayPal completion.', $json);
                }
            } catch (\Exception $e) {
                Log::warning('PayPal status exception: '.$e->getMessage());
            }
        }

        if ($payment->isSuccessful()) {
            return PaymentResult::successful($payment->reference, 'Payment is confirmed.');
        }

        return PaymentResult::pending($orderId, 'Payment is pending.');
    }

    /**
     * Process refund via PayPal v2 captures refund API.
     */
    public function refund(Payment $payment, int $amountKes, string $reason): RefundResult
    {
        $captureId = $payment->reference;
        $order = $payment->order;
        $exchangeRate = $order->exchange_rate_used ?: 130.00;
        $usdCents = (int) ceil(($amountKes / $exchangeRate));
        $usdAmount = number_format($usdCents / 100, 2, '.', '');

        $token = $this->getAccessToken();
        if ($token && $this->clientId !== 'mock_paypal_client_id' && $captureId) {
            try {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->post($this->getBaseUrl()."/v2/payments/captures/{$captureId}/refund", [
                        'amount' => [
                            'value' => $usdAmount,
                            'currency_code' => 'USD',
                        ],
                        'note_to_payer' => $reason,
                    ]);

                $json = $response->json();
                if ($response->successful() && ($json['status'] ?? '') === 'COMPLETED') {
                    return RefundResult::successful(
                        $json['id'] ?? 'PP-REF-'.strtoupper(Str::random(10)),
                        'PayPal refund processed successfully.',
                        $json
                    );
                }

                return RefundResult::failed($json['message'] ?? 'PayPal refund failed.', $json);
            } catch (\Exception $e) {
                Log::error('PayPal refund exception: '.$e->getMessage());
                return RefundResult::failed('Could not process PayPal refund: '.$e->getMessage());
            }
        }

        // Test mode simulation
        $mockRefundId = 'PP-REF-'.strtoupper(Str::random(12));
        return RefundResult::successful(
            $mockRefundId,
            'PayPal refund simulated successfully in test mode.',
            ['refund_id' => $mockRefundId, 'usd_amount' => $usdAmount]
        );
    }
}
