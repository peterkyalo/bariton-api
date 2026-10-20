<?php

namespace App\Services;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfricasTalkingSmsService
{
    protected string $username;

    protected string $apiKey;

    protected ?string $senderId;

    public function __construct()
    {
        $this->username = config('services.africastalking.username', 'sandbox');
        $this->apiKey = config('services.africastalking.api_key', '');
        $this->senderId = config('services.africastalking.sender_id');
    }

    /**
     * Normalizes a Kenyan phone number to 2547XXXXXXXX or international format with leading +.
     */
    public static function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^\d]/', '', $phone);

        if (str_starts_with($cleaned, '0')) {
            $cleaned = '254'.substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '7') || str_starts_with($cleaned, '1')) {
            $cleaned = '254'.$cleaned;
        }

        return '+'.$cleaned;
    }

    /**
     * Send an SMS and record in notification_logs.
     */
    public function send(string $to, string $message, ?string $notifiableType = null, ?int $notifiableId = null, string $type = 'sms'): NotificationLog
    {
        $normalizedRecipient = self::normalizePhone($to);

        $log = NotificationLog::create([
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'channel' => 'sms',
            'notification_type' => $type,
            'recipient' => $normalizedRecipient,
            'status' => 'queued',
        ]);

        // If in test/local without real API key, simulate success
        if (app()->environment('testing') || empty($this->apiKey)) {
            $mockMessageId = 'AT-SIM-'.uniqid();
            $log->update([
                'status' => 'delivered',
                'provider_message_id' => $mockMessageId,
                'sent_at' => now(),
            ]);

            Log::info("Africa's Talking SMS simulated to {$normalizedRecipient}: {$message}");

            return $log;
        }

        try {
            $isSandbox = strtolower($this->username) === 'sandbox';
            $url = $isSandbox
                ? 'https://api.sandbox.africastalking.com/version1/messaging'
                : 'https://api.africastalking.com/version1/messaging';

            $postData = [
                'username' => $this->username,
                'to' => $normalizedRecipient,
                'message' => $message,
            ];

            if ($this->senderId && ! $isSandbox) {
                $postData['from'] = $this->senderId;
            }

            $response = Http::asForm()
                ->withHeaders([
                    'apiKey' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(15)
                ->post($url, $postData);

            if ($response->successful()) {
                $data = $response->json();
                $recipientData = $data['SMSMessageData']['Recipients'][0] ?? null;
                $msgId = $recipientData['messageId'] ?? null;
                $status = ($recipientData['status'] ?? '') === 'Success' ? 'sent' : 'failed';

                $log->update([
                    'status' => $status,
                    'provider_message_id' => $msgId,
                    'sent_at' => now(),
                    'error' => $status === 'failed' ? json_encode($data) : null,
                ]);
            } else {
                $log->update([
                    'status' => 'failed',
                    'error' => 'HTTP '.$response->status().': '.$response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }

        return $log;
    }
}
