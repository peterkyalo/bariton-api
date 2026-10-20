<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplatedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customSubject,
        public string $bodyContent,
        public array $variables = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->renderHtml(),
        );
    }

    protected function renderHtml(): string
    {
        $safeBody = nl2br(e($this->bodyContent));

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$this->customSubject}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0b0f19; color: #f1f5f9; margin: 0; padding: 40px 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #131b2e; border: 1px solid #1e293b; border-radius: 12px; overflow: hidden; }
        .header { background: #0f172a; padding: 24px; text-align: center; border-bottom: 1px solid #1e293b; }
        .header h1 { margin: 0; font-size: 20px; color: #f59e0b; letter-spacing: 1px; }
        .content { padding: 32px 24px; line-height: 1.6; font-size: 14px; color: #cbd5e1; }
        .footer { background: #0f172a; padding: 16px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid #1e293b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BARITON MUSIC ACADEMY</h1>
        </div>
        <div class="content">
            {$safeBody}
        </div>
        <div class="footer">
            &copy; 2026 Bariton Music Academy, Nairobi Kenya. All rights reserved.
        </div>
    </div>
</body>
</html>
HTML;
    }
}
