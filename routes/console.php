<?php

use App\Services\EventReminderService;
use App\Services\TutoringExpiryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:test {email}', function (string $email) {
    $this->info("Sending test email to {$email} using " . config('mail.mailers.smtp.host') . ":" . config('mail.mailers.smtp.port') . "...");
    try {
        Illuminate\Support\Facades\Mail::raw('This is a test email from Bariton Music Academy (cPanel SMTP).', function ($msg) use ($email) {
            $msg->to($email)->subject('SMTP Mail Test - Bariton Music Academy');
        });
        $this->info("Test email successfully sent to {$email}!");
    } catch (\Throwable $e) {
        $this->error("Failed to send email: " . $e->getMessage());
    }
})->purpose('Send a test email via SMTP to verify configuration');

/*
|--------------------------------------------------------------------------
| Scheduled Cron Tasks
| Run via system cron: * * * * * cd /path/to/api && php artisan schedule:run >> /dev/null 2>&1
|--------------------------------------------------------------------------
*/

// Daily tutoring packages expiry sweep
Schedule::call(function () {
    app(TutoringExpiryService::class)->expirePackages();
})->dailyAt('00:01')->name('tutoring:expire-packages');

// Daily physical event reminders (24h in advance)
Schedule::call(function () {
    app(EventReminderService::class)->sendUpcomingEventReminders();
})->dailyAt('08:00')->name('events:send-reminders');

// Nightly database and storage backup
Schedule::command('app:backup')->dailyAt('02:00')->name('app:nightly-backup');

// Prune expired tokens
Schedule::command('sanctum:prune-expired --hours=48')->daily()->name('sanctum:prune-expired');
