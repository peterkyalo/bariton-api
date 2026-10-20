<?php

namespace App\Services;

use App\Mail\TemplatedNotificationMail;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationTemplateService
{
    public function __construct(
        protected AfricasTalkingSmsService $smsService
    ) {}

    /**
     * Interpolate double-brace placeholders: {{ variable }} with values.
     */
    public function interpolate(string $content, array $variables): string
    {
        foreach ($variables as $key => $val) {
            $replacement = is_array($val) ? json_encode($val) : (string) $val;
            $content = preg_replace('/\{\{\s*'.preg_quote($key, '/').'\s*\}\}/i', $replacement, $content);
        }

        return $content;
    }

    /**
     * Dispatch notification using a database template.
     */
    public function send(
        string $key,
        User $user,
        array $variables = [],
        ?Model $notifiable = null
    ): array {
        $template = NotificationTemplate::where('key', $key)->first();

        if (! $template || ! $template->is_active) {
            Log::warning("Notification template [{$key}] not found or inactive.");

            return [
                'status' => 'skipped',
                'reason' => 'template_not_found_or_inactive',
            ];
        }

        // Standard default variables if not provided
        $mergedVariables = array_merge([
            'student_name' => $user->name,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'app_url' => config('app.url', 'https://app.bmas.com'),
        ], $variables);

        // Check user preferences
        if (! $user->allowsNotification($key, $template->category)) {
            NotificationLog::create([
                'notifiable_type' => $notifiable ? get_class($notifiable) : User::class,
                'notifiable_id' => $notifiable ? $notifiable->getKey() : $user->id,
                'channel' => $template->channel,
                'notification_type' => $key,
                'recipient' => $user->email,
                'status' => 'opted_out',
                'error' => 'User opted out of optional notification.',
                'sent_at' => now(),
            ]);

            return [
                'status' => 'opted_out',
                'template' => $key,
            ];
        }

        $results = [];

        // 1. Send Email Channel
        if (in_array($template->channel, ['email', 'both'], true) && ! empty($user->email)) {
            try {
                $subject = $this->interpolate($template->email_subject ?? 'Bariton Music Academy Notification', $mergedVariables);
                $body = $this->interpolate($template->email_body ?? '', $mergedVariables);

                Mail::to($user->email)->send(new TemplatedNotificationMail($subject, $body, $mergedVariables));

                NotificationLog::create([
                    'notifiable_type' => $notifiable ? get_class($notifiable) : User::class,
                    'notifiable_id' => $notifiable ? $notifiable->getKey() : $user->id,
                    'channel' => 'email',
                    'notification_type' => $key,
                    'recipient' => $user->email,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $results['email'] = 'sent';
            } catch (\Throwable $e) {
                Log::error("Failed sending email notification [{$key}] to {$user->email}: ".$e->getMessage());

                NotificationLog::create([
                    'notifiable_type' => $notifiable ? get_class($notifiable) : User::class,
                    'notifiable_id' => $notifiable ? $notifiable->getKey() : $user->id,
                    'channel' => 'email',
                    'notification_type' => $key,
                    'recipient' => $user->email,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'sent_at' => now(),
                ]);

                $results['email'] = 'failed';
            }
        }

        // 2. Send SMS Channel
        if (in_array($template->channel, ['sms', 'both'], true) && ! empty($user->phone)) {
            try {
                $smsBody = $this->interpolate($template->sms_body ?? '', $mergedVariables);

                if (! empty($smsBody)) {
                    $this->smsService->send(
                        to: $user->phone,
                        message: $smsBody,
                        notifiableType: $notifiable ? get_class($notifiable) : User::class,
                        notifiableId: $notifiable ? $notifiable->getKey() : $user->id,
                        type: $key
                    );
                    $results['sms'] = 'sent';
                }
            } catch (\Throwable $e) {
                Log::error("Failed sending SMS notification [{$key}] to {$user->phone}: ".$e->getMessage());
                $results['sms'] = 'failed';
            }
        }

        return [
            'status' => 'dispatched',
            'template' => $key,
            'channels' => $results,
        ];
    }
}
