<?php

namespace App\Mail;

use App\Models\ContactInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewContactInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactInquiry $inquiry
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Contact Inquiry: {$this->inquiry->subject} [{$this->inquiry->name}]",
            replyTo: [
                new Address($this->inquiry->email, $this->inquiry->name),
            ],
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
        $name = e($this->inquiry->name);
        $email = e($this->inquiry->email);
        $phone = e($this->inquiry->phone ?: 'Not provided');
        $subject = e($this->inquiry->subject);
        $message = nl2br(e($this->inquiry->message));
        $date = now()->setTimezone('Africa/Nairobi')->format('d M Y, h:i A T');
        $adminUrl = rtrim((string) env('FRONTEND_URL', 'https://app.bmas.co.ke'), '/') . '/admin/cms/inquiries';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Contact Inquiry</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #060913; color: #f1f5f9; margin: 0; padding: 30px 15px; }
        .container { max-width: 600px; margin: 0 auto; background: #0c1428; border: 1px solid #1e2c52; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .header { background: #070a16; padding: 24px; text-align: center; border-bottom: 1px solid #1e2c52; }
        .header h1 { margin: 0; font-size: 20px; color: #d49b38; letter-spacing: 1.5px; font-weight: 700; }
        .header p { margin: 6px 0 0; font-size: 11px; text-transform: uppercase; color: #8292be; letter-spacing: 2px; }
        .content { padding: 30px 24px; font-size: 14px; line-height: 1.6; color: #d8def0; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; background: rgba(212, 155, 56, 0.15); border: 1px solid rgba(212, 155, 56, 0.3); color: #d49b38; font-size: 11px; font-weight: 600; margin-bottom: 16px; }
        .detail-card { background: #0f172e; border: 1px solid #1e2c52; border-radius: 12px; padding: 18px; margin: 18px 0; }
        .detail-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 13px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #8292be; font-weight: 600; }
        .detail-value { color: #ffffff; text-align: right; }
        .message-box { background: #141f3c; border-left: 3px solid #d49b38; border-radius: 8px; padding: 16px; margin: 20px 0; font-size: 14px; color: #f1f5f9; line-height: 1.7; }
        .btn { display: block; width: fit-content; margin: 24px auto 0; padding: 12px 28px; background: linear-gradient(135deg, #d49b38, #b88228); color: #060913; text-decoration: none; font-weight: 700; font-size: 13px; border-radius: 10px; text-align: center; }
        .footer { background: #070a16; padding: 18px; text-align: center; font-size: 11px; color: #4c5f91; border-top: 1px solid #1e2c52; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BARITON MUSIC ACADEMY</h1>
            <p>New Public Contact Inquiry</p>
        </div>
        <div class="content">
            <span class="badge">Inquiry Received</span>
            <p style="margin-top: 0;">A visitor has submitted a message via the public website contact form:</p>

            <div class="detail-card">
                <div class="detail-row">
                    <span class="detail-label">Sender Name</span>
                    <span class="detail-value"><strong>{$name}</strong></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email Address</span>
                    <span class="detail-value"><a href="mailto:{$email}" style="color: #60a5fa; text-decoration: none;">{$email}</a></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone / WhatsApp</span>
                    <span class="detail-value">{$phone}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Subject</span>
                    <span class="detail-value">{$subject}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time</span>
                    <span class="detail-value">{$date}</span>
                </div>
            </div>

            <p style="margin-bottom: 6px; font-weight: 600; color: #ffffff;">Message Content:</p>
            <div class="message-box">
                {$message}
            </div>

            <p style="font-size: 12px; color: #8292be; margin-top: 20px;">
                <em>Tip: You can reply directly to this email to write back to {$name} ({$email}).</em>
            </p>

            <a href="{$adminUrl}" class="btn">View in Admin Panel</a>
        </div>
        <div class="footer">
            &copy; 2026 Bariton Music Academy &bull; Kilimani, Nairobi, Kenya
        </div>
    </div>
</body>
</html>
HTML;
    }
}
