<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StripePaymentGateway implements PaymentGatewayInterface
{
    protected string $apiKey;

    protected string $webhookSecret;

    public function __construct()
    {
        $this->apiKey = (string) config('services.stripe.secret', '');
        $this->webhookSecret = (string) config('services.stripe.webhook_secret', '');
    }

    /**
     * Initiate Stripe PaymentIntent in KES.
     */
    public function initiate(Order $order, array $params = []): PaymentResult
    {
        $idempotencyKey = $params['idempotency_key'] ?? 'stripe_pi_order_'.$order->id.'_'.Str::random(10);
        $amountKes = $order->total_kes; // minor units in KES

        // In test mode or when credentials provided, create real or simulated PaymentIntent
        if (! empty($this->apiKey) && $this->apiKey !== 'sk_test_mock') {
            try {
                $response = Http::withToken($this->apiKey)
                    ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                    ->asForm()
                    ->timeout(15)
                    ->post('https://api.stripe.com/v1/payment_intents', [
                        'amount' => $amountKes,
                        'currency' => 'kes',
                        'description' => "Bariton Academy Order {$order->order_number}",
                        'metadata' => [
                            'order_id' => $order->id,
                            'order_uuid' => $order->uuid,
                            'user_id' => $order->user_id,
                            'user_email' => $order->user->email ?? '',
                        ],
                        'automatic_payment_methods[enabled]' => 'true',
                    ]);

                $json = $response->json();

                if ($response->successful()) {
                    return PaymentResult::pending(
                        reference: $json['id'],
                        message: 'Stripe PaymentIntent created.',
                        data: [
                            'client_secret' => $json['client_secret'],
                            'payment_intent_id' => $json['id'],
                            'idempotency_key' => $idempotencyKey,
                        ]
                    );
                }

                return PaymentResult::failed($json['error']['message'] ?? 'Failed to create Stripe PaymentIntent.', $json);
            } catch (\Exception $e) {
                Log::error('Stripe initiate exception: '.$e->getMessage());

                return PaymentResult::failed('Could not connect to Stripe payment gateway: '.$e->getMessage());
            }
        }

        // Test mode simulation
        $mockPiId = 'pi_test_'.Str::random(24);
        $mockSecret = $mockPiId.'_secret_'.Str::random(24);

        return PaymentResult::pending(
            reference: $mockPiId,
            message: 'Stripe PaymentIntent created (test mode).',
            data: [
                'client_secret' => $mockSecret,
                'payment_intent_id' => $mockPiId,
                'idempotency_key' => $idempotencyKey,
            ]
        );
    }

    /**
     * Confirm payment from Stripe webhook payload.
     */
    public function confirm(Payment $payment, array $payload = []): PaymentResult
    {
        $status = $payload['data']['object']['status'] ?? $payload['status'] ?? null;
        $piId = $payload['data']['object']['id'] ?? $payload['id'] ?? $payment->stripe_payment_intent_id;

        if ($status === 'succeeded') {
            $reference = $piId ?: 'ch_stripe_'.Str::random(16);

            return PaymentResult::successful($reference, 'Payment confirmed via Stripe.', [
                'stripe_payload' => $payload,
                'payment_intent' => $piId,
            ]);
        }

        return PaymentResult::failed('Payment has not succeeded on Stripe.', ['payload' => $payload]);
    }

    /**
     * Query PaymentIntent status from Stripe API.
     */
    public function status(Payment $payment): PaymentResult
    {
        $piId = $payment->stripe_payment_intent_id ?: $payment->reference;

        if (empty($piId)) {
            return PaymentResult::failed('No Stripe PaymentIntent ID associated with payment.');
        }

        if (! empty($this->apiKey) && $this->apiKey !== 'sk_test_mock') {
            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout(10)
                    ->get("https://api.stripe.com/v1/payment_intents/{$piId}");

                $json = $response->json();
                if ($response->successful()) {
                    if (($json['status'] ?? '') === 'succeeded') {
                        return PaymentResult::successful($piId, 'Payment confirmed via Stripe.', $json);
                    }

                    return PaymentResult::pending($piId, 'Payment is awaiting completion.', $json);
                }
            } catch (\Exception $e) {
                Log::warning('Stripe status check exception: '.$e->getMessage());
            }
        }

        if ($payment->isSuccessful()) {
            return PaymentResult::successful($payment->reference, 'Payment is confirmed.');
        }

        return PaymentResult::pending($piId, 'Payment is pending.');
    }

    /**
     * Process refund via Stripe Refund API with idempotency key.
     */
    public function refund(Payment $payment, int $amountKes, string $reason): RefundResult
    {
        $piId = $payment->stripe_payment_intent_id ?: $payment->reference;
        $idempotencyKey = 'stripe_ref_'.$payment->id.'_'.$amountKes.'_'.Str::random(8);

        if (! empty($this->apiKey) && $this->apiKey !== 'sk_test_mock' && $piId) {
            try {
                $response = Http::withToken($this->apiKey)
                    ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                    ->asForm()
                    ->timeout(15)
                    ->post('https://api.stripe.com/v1/refunds', [
                        'payment_intent' => $piId,
                        'amount' => $amountKes,
                        'reason' => 'requested_by_customer',
                        'metadata' => [
                            'order_id' => $payment->order_id,
                            'reason' => $reason,
                        ],
                    ]);

                $json = $response->json();
                if ($response->successful()) {
                    return RefundResult::successful(
                        $json['id'] ?? 're_'.Str::random(20),
                        'Stripe card refund processed successfully.',
                        $json
                    );
                }

                return RefundResult::failed($json['error']['message'] ?? 'Stripe refund call failed.');
            } catch (\Exception $e) {
                Log::error('Stripe refund exception: '.$e->getMessage());

                return RefundResult::failed('Could not process Stripe refund: '.$e->getMessage());
            }
        }

        // Test mode simulation
        $mockRefundId = 're_test_'.Str::random(20);

        return RefundResult::successful(
            $mockRefundId,
            'Stripe card refund simulated successfully in test mode.',
            ['refund_id' => $mockRefundId, 'amount_kes' => $amountKes]
        );
    }
}
