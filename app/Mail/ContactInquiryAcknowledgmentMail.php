<?php

namespace App\Mail;

use App\Models\ContactInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactInquiryAcknowledgmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactInquiry $inquiry
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "We have received your message - Bariton Music Academy",
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
        $subject = e($this->inquiry->subject);
        $catalogUrl = rtrim((string) env('FRONTEND_URL', 'https://app.bmas.co.ke'), '/') . '/courses';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inquiry Received</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #060913; color: #f1f5f9; margin: 0; padding: 30px 15px; }
        .container { max-width: 600px; margin: 0 auto; background: #0c1428; border: 1px solid #1e2c52; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .header { background: #070a16; padding: 24px; text-align: center; border-bottom: 1px solid #1e2c52; }
        .header h1 { margin: 0; font-size: 20px; color: #d49b38; letter-spacing: 1.5px; font-weight: 700; }
        .content { padding: 30px 24px; font-size: 14px; line-height: 1.6; color: #d8def0; }
        .btn { display: block; width: fit-content; margin: 24px auto 0; padding: 12px 28px; background: linear-gradient(135deg, #d49b38, #b88228); color: #060913; text-decoration: none; font-weight: 700; font-size: 13px; border-radius: 10px; text-align: center; }
        .footer { background: #070a16; padding: 18px; text-align: center; font-size: 11px; color: #4c5f91; border-top: 1px solid #1e2c52; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BARITON MUSIC ACADEMY</h1>
        </div>
        <div class="content">
            <h2 style="color: #ffffff; font-size: 18px; margin-top: 0;">Hello {$name},</h2>
            <p>Thank you for reaching out to Bariton Music Academy! We have received your inquiry regarding <strong>"{$subject}"</strong>.</p>
            <p>One of our conservatory admissions counselors will review your message and get back to you within 24 business hours.</p>
            <p>In the meantime, feel free to explore our conservatory course offerings, masterclasses, and bootcamps:</p>
            <a href="{$catalogUrl}" class="btn">Explore Course Catalog</a>
        </div>
        <div class="footer">
            &copy; 2026 Bariton Music Academy &bull; Kilimani, Nairobi, Kenya<br>
            Phone: +254 712 345 678 &bull; Email: admin@bmas.co.ke
        </div>
    </div>
</body>
</html>
HTML;
    }
}
