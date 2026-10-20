<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuardianConfirmationRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $student,
        public string $guardianName,
        public string $plainToken
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $confirmUrl = "{$frontendUrl}/guardian/confirm?token={$this->plainToken}";

        return (new MailMessage)
            ->subject('Guardian Consent Required for Bariton Music Academy Account')
            ->greeting("Dear {$this->guardianName},")
            ->line("{$this->student->name} (born {$this->student->date_of_birth?->format('F j, Y')}) has registered for a student account on the Bariton Music Academy online learning platform and designated you as their parent or legal guardian.")
            ->line('In compliance with the Kenya Data Protection Act 2019 and academy child safety policies, we require verifiable parental/guardian consent before this minor account can be activated.')
            ->action('Confirm & Activate Student Account', $confirmUrl)
            ->line('If you did not authorize this registration or believe this was done in error, you may simply disregard this email.');
    }
}
