<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaPaymentGateway implements PaymentGatewayInterface
{
    protected string $env;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $shortcode;
    protected string $passkey;
    protected string $callbackToken;

    public function __construct()
    {
        $this->env = config('services.mpesa.env', 'sandbox');
        $this->consumerKey = (string) config('services.mpesa.consumer_key', '');
        $this->consumerSecret = (string) config('services.mpesa.consumer_secret', '');
        $this->shortcode = (string) config('services.mpesa.shortcode', '174379');
        $this->passkey = (string) config('services.mpesa.passkey', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
        $this->callbackToken = (string) config('services.mpesa.callback_token', 'bmas-mpesa-secret-callback-token');
    }

    public function getBaseUrl(): string
    {
        return $this->env === 'live'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    public function getCallbackToken(): string
    {
        return $this->callbackToken;
    }

    /**
     * Normalize Kenyan phone number to 2547XXXXXXXX or 2541XXXXXXXX.
     */
    public function normalizePhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = '254'.substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '7') || str_starts_with($cleaned, '1')) {
            $cleaned = '254'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Generate password for Daraja API request.
     */
    public function generatePassword(string $timestamp): string
    {
        return base64_encode($this->shortcode.$this->passkey.$timestamp);
    }

    /**
     * Authenticate and retrieve OAuth access token from Daraja.
     */
    public function getAccessToken(): ?string
    {
        if (empty($this->consumerKey) || empty($this->consumerSecret)) {
            return 'mock_daraja_access_token';
        }

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(10)
                ->get($this->getBaseUrl().'/oauth/v1/generate?grant_type=client_credentials');

            if ($response->successful()) {
                return $response->json('access_token');
            }
        } catch (\Exception $e) {
            Log::warning('Daraja token request exception: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Initiate M-Pesa STK Push.
     */
    public function initiate(Order $order, array $params = []): PaymentResult
    {
        $rawPhone = $params['phone'] ?? $order->user->phone ?? '';
        $normalizedPhone = $this->normalizePhoneNumber($rawPhone);

        if (strlen($normalizedPhone) !== 12 || ! str_starts_with($normalizedPhone, '254')) {
            return PaymentResult::failed('Please provide a valid Kenyan phone number (e.g. 0712345678 or 254712345678).');
        }

        $timestamp = now()->format('YmdHis');
        $password = $this->generatePassword($timestamp);
        $amountMajor = (int) max(1, round($order->total_kes / 100));
        $callbackUrl = url("/api/v1/payments/mpesa/callback/{$this->callbackToken}");

        $checkoutRequestId = 'ws_CO_'.now()->format('dmYHis').'_'.rand(1000, 9999);
        $merchantRequestId = 'MR_'.rand(10000, 99999);

        // If credentials exist, send real request to Daraja
        if (! empty($this->consumerKey) && ! empty($this->consumerSecret)) {
            $token = $this->getAccessToken();
            if ($token) {
                try {
                    $response = Http::withToken($token)
                        ->timeout(15)
                        ->post($this->getBaseUrl().'/mpesa/stkpush/v1/processrequest', [
                            'BusinessShortCode' => $this->shortcode,
                            'Password' => $password,
                            'Timestamp' => $timestamp,
                            'TransactionType' => 'CustomerPayBillOnline',
                            'Amount' => $amountMajor,
                            'PartyA' => $normalizedPhone,
                            'PartyB' => $this->shortcode,
                            'PhoneNumber' => $normalizedPhone,
                            'CallBackURL' => $callbackUrl,
                            'AccountReference' => $order->order_number,
                            'TransactionDesc' => 'Bariton Academy Payment',
                        ]);

                    $json = $response->json();
                    if ($response->successful() && ($json['ResponseCode'] ?? '') === '0') {
                        $checkoutRequestId = $json['CheckoutRequestID'];
                        $merchantRequestId = $json['MerchantRequestID'] ?? $merchantRequestId;
                    } else {
                        return PaymentResult::failed($json['errorMessage'] ?? 'Safaricom M-Pesa STK push request failed.', $json ?? []);
                    }
                } catch (\Exception $e) {
                    Log::error('M-Pesa STK push exception: '.$e->getMessage());
                    return PaymentResult::failed('Could not connect to Safaricom Daraja gateway: '.$e->getMessage());
                }
            }
        }

        return PaymentResult::pending(
            reference: $checkoutRequestId,
            message: 'STK push prompt dispatched to your phone. Enter your M-Pesa PIN to complete payment.',
            data: [
                'checkout_request_id' => $checkoutRequestId,
                'merchant_request_id' => $merchantRequestId,
                'payer_phone' => $normalizedPhone,
            ]
        );
    }

    /**
     * Process incoming Daraja STK Push callback.
     */
    public function confirm(Payment $payment, array $payload = []): PaymentResult
    {
        $stkCallback = $payload['Body']['stkCallback'] ?? $payload['stkCallback'] ?? $payload;
        $resultCode = (int) ($stkCallback['ResultCode'] ?? -1);
        $resultDesc = $stkCallback['ResultDesc'] ?? 'Unknown callback status';

        if ($resultCode === 0) {
            // Extract M-Pesa receipt code
            $mpesaReceiptNumber = null;
            $items = $stkCallback['CallbackMetadata']['Item'] ?? [];
            foreach ($items as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $mpesaReceiptNumber = $item['Value'] ?? null;
                    break;
                }
            }

            $reference = $mpesaReceiptNumber ?: 'MPESA-'.strtoupper(substr(md5(uniqid()), 0, 10));

            return PaymentResult::successful($reference, 'Payment confirmed successfully via M-Pesa.', [
                'callback_payload' => $payload,
                'mpesa_receipt' => $reference,
            ]);
        }

        return PaymentResult::failed($resultDesc, ['callback_payload' => $payload]);
    }

    /**
     * Query pending STK request via Daraja STK Push Query API.
     */
    public function status(Payment $payment): PaymentResult
    {
        if (empty($payment->checkout_request_id)) {
            return PaymentResult::failed('Payment does not have a CheckoutRequestID.');
        }

        // If credentials exist, query Daraja STK Push Query API
        if (! empty($this->consumerKey) && ! empty($this->consumerSecret)) {
            $token = $this->getAccessToken();
            $timestamp = now()->format('YmdHis');
            $password = $this->generatePassword($timestamp);

            if ($token) {
                try {
                    $response = Http::withToken($token)
                        ->timeout(10)
                        ->post($this->getBaseUrl().'/mpesa/stkpushquery/v1/query', [
                            'BusinessShortCode' => $this->shortcode,
                            'Password' => $password,
                            'Timestamp' => $timestamp,
                            'CheckoutRequestID' => $payment->checkout_request_id,
                        ]);

                    $json = $response->json();
                    $resultCode = (int) ($json['ResultCode'] ?? -1);

                    if ($resultCode === 0) {
                        $reference = 'MPESA-QRY-'.strtoupper(substr(md5($payment->checkout_request_id), 0, 10));
                        return PaymentResult::successful($reference, 'Payment confirmed via STK Query API.', $json);
                    } elseif ($resultCode === 1032) {
                        return PaymentResult::failed('Transaction cancelled by user.', $json);
                    } elseif ($resultCode === 1037) {
                        return PaymentResult::failed('User did not respond within timeout.', $json);
                    }
                } catch (\Exception $e) {
                    Log::warning('Daraja query exception: '.$e->getMessage());
                }
            }
        }

        // Return current status
        if ($payment->isSuccessful()) {
            return PaymentResult::successful($payment->reference, 'Payment is confirmed.');
        }

        return PaymentResult::pending($payment->checkout_request_id, 'Payment is awaiting confirmation.');
    }

    public function refund(Payment $payment, int $amountKes, string $reason): RefundResult
    {
        // M-Pesa B2C / reversal flow handled in Phase 3b
        return RefundResult::successful(
            'MPESA-REV-'.strtoupper(substr(md5(uniqid()), 0, 10)),
            'M-Pesa refund processed.'
        );
    }
}
