<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'registration',
                'name' => 'Account Registration Welcome',
                'category' => 'security_payment',
                'channel' => 'both',
                'email_subject' => 'Welcome to Bariton Music Academy, {{ student_name }}!',
                'email_body' => "Dear {{ student_name }},\n\nWelcome to Bariton Music Academy. Your student account has been created successfully.\n\nYou can now log in, explore our instrument syllabi, enroll in world-class courses, and book physical masterclasses.\n\nSign in here: {{ app_url }}/login\n\nWarm regards,\nBariton Music Academy Team",
                'sms_body' => 'Welcome to Bariton Music Academy, {{ student_name }}! Your account is active. Log in at {{ app_url }} to begin your musical journey.',
                'variables' => ['student_name', 'app_url'],
                'is_active' => true,
            ],
            [
                'key' => 'payment_received',
                'name' => 'Payment Received Confirmation',
                'category' => 'security_payment',
                'channel' => 'both',
                'email_subject' => 'Payment Confirmed: {{ payment_reference }}',
                'email_body' => "Dear {{ student_name }},\n\nWe have received your payment of {{ amount_formatted }} via {{ payment_method }}.\n\nReference: {{ payment_reference }}\nOrder: {{ order_reference }}\n\nYour items have been unlocked and are ready on your student dashboard.\n\nThank you for choosing Bariton Music Academy.",
                'sms_body' => 'Bariton: We received {{ amount_formatted }} for order {{ order_reference }} via {{ payment_method }}. Ref: {{ payment_reference }}. Thank you!',
                'variables' => ['student_name', 'amount_formatted', 'payment_method', 'payment_reference', 'order_reference'],
                'is_active' => true,
            ],
            [
                'key' => 'receipt_issued',
                'name' => 'Sequential Tax Receipt Issued',
                'category' => 'security_payment',
                'channel' => 'both',
                'email_subject' => 'Official Receipt #{{ receipt_number }} - Bariton Music Academy',
                'email_body' => "Dear {{ student_name }},\n\nYour official sequential electronic receipt #{{ receipt_number }} has been generated.\n\nTotal Paid: {{ amount_formatted }}\nInclusive of 16% VAT: {{ vat_amount }}\nPayment Reference: {{ payment_reference }}\n\nYou may view and download your full printable PDF receipt from your student orders portal.\n\nBariton Music Academy Finance Office",
                'sms_body' => 'Bariton: Official receipt #{{ receipt_number }} generated for {{ amount_formatted }}. View details in your student portal.',
                'variables' => ['student_name', 'receipt_number', 'amount_formatted', 'vat_amount', 'payment_reference'],
                'is_active' => true,
            ],
            [
                'key' => 'refund_decision',
                'name' => 'Refund Decision Notice',
                'category' => 'security_payment',
                'channel' => 'both',
                'email_subject' => 'Update on your refund request: {{ decision_status }}',
                'email_body' => "Dear {{ student_name }},\n\nYour refund request for order {{ order_reference }} has been {{ decision_status }}.\n\nNotes from Finance: {{ review_notes }}\n\nIf approved, a credit note ({{ credit_note_number }}) has been issued and funds returned through your original payment method.\n\nBariton Music Academy Finance Directorate",
                'sms_body' => 'Bariton: Your refund request for order {{ order_reference }} was {{ decision_status }}. Reason: {{ review_notes }}',
                'variables' => ['student_name', 'order_reference', 'decision_status', 'review_notes', 'credit_note_number'],
                'is_active' => true,
            ],
            [
                'key' => 'assignment_graded',
                'name' => 'Assignment Grading Results',
                'category' => 'academic',
                'channel' => 'both',
                'email_subject' => 'Grading Feedback: {{ assignment_title }} (Score: {{ score }}/{{ max_score }})',
                'email_body' => "Dear {{ student_name }},\n\nYour instructor {{ instructor_name }} has graded your submission for {{ assignment_title }}.\n\nFinal Score: {{ score }}/{{ max_score }} ({{ percentage }}%)\nInstructor Feedback: {{ feedback }}\n\nReview rubric breakdowns on your course studio.\n\nBariton Academy Academic Team",
                'sms_body' => 'Bariton: {{ assignment_title }} graded by {{ instructor_name }}. Score: {{ score }}/{{ max_score }}. Log in to view rubric feedback.',
                'variables' => ['student_name', 'instructor_name', 'assignment_title', 'score', 'max_score', 'percentage', 'feedback'],
                'is_active' => true,
            ],
            [
                'key' => 'certificate_awarded',
                'name' => 'Digital Certificate Awarded',
                'category' => 'academic',
                'channel' => 'both',
                'email_subject' => 'Congratulations! Certificate of Completion: {{ course_title }}',
                'email_body' => "Dear {{ student_name }},\n\nCongratulations on successfully completing {{ course_title }}!\n\nYour official verifiable digital certificate has been issued.\nCertificate Number: {{ certificate_number }}\nVerification Code: {{ verification_code }}\n\nAnyone can verify your achievement at:\n{{ verification_url }}\n\nKeep inspiring the musical world!\nBariton Music Academy Board of Examiners",
                'sms_body' => 'Bariton Congratulations! Certificate awarded for {{ course_title }}. Verification code: {{ verification_code }}. Verify at {{ verification_url }}',
                'variables' => ['student_name', 'course_title', 'certificate_number', 'verification_code', 'verification_url'],
                'is_active' => true,
            ],
            [
                'key' => 'event_reminder',
                'name' => 'Physical Bootcamp & Workshop Reminder',
                'category' => 'events',
                'channel' => 'both',
                'email_subject' => 'Event Reminder: {{ event_title }} is starting soon!',
                'email_body' => "Dear {{ student_name }},\n\nThis is a friendly reminder for the upcoming {{ event_title }}.\n\nVenue: {{ venue }}\nStart: {{ start_datetime }}\nEnd: {{ end_datetime }}\nAccommodation: {{ accommodation_status }}\n\nPlease arrive 15 minutes before start time for attendee check-in. Don't forget your instrument and workbook!\n\nBariton Music Academy Events Office",
                'sms_body' => 'Bariton Reminder: {{ event_title }} at {{ venue }} begins on {{ start_datetime }}. Check-in begins 15 mins prior.',
                'variables' => ['student_name', 'event_title', 'venue', 'start_datetime', 'end_datetime', 'accommodation_status'],
                'is_active' => true,
            ],
            [
                'key' => 'session_reminder',
                'name' => '1-on-1 Tutoring Session Reminder',
                'category' => 'tutoring',
                'channel' => 'both',
                'email_subject' => 'Upcoming 1-on-1 Lesson with {{ instructor_name }}',
                'email_body' => "Dear {{ student_name }},\n\nYour private tutoring session with {{ instructor_name }} is scheduled for {{ session_datetime }} (Africa/Nairobi EAT).\n\nMode: {{ session_mode }}\nLocation / Meeting Link: {{ meeting_info }}\n\nPlease ensure your camera and audio are tested prior to joining.\n\nBariton Music Academy Tutoring Studio",
                'sms_body' => 'Bariton Tutoring: Private lesson with {{ instructor_name }} on {{ session_datetime }}. Mode: {{ session_mode }}. Link/Room: {{ meeting_info }}',
                'variables' => ['student_name', 'instructor_name', 'session_datetime', 'session_mode', 'meeting_info'],
                'is_active' => true,
            ],
            [
                'key' => 'course_assigned',
                'name' => 'Faculty Course Assignment Notice',
                'category' => 'academic',
                'channel' => 'both',
                'email_subject' => 'Faculty Assignment: {{ course_title }}',
                'email_body' => "Dear {{ instructor_name }},\n\nYou have been assigned as an instructor to teach {{ course_title }}.\n\nYou can now access your Faculty Studio, manage course levels, and create course curriculum modules and lessons.\n\nOpen your studio here: {{ app_url }}/instructor/courses/{{ course_id }}\n\nBariton Music Academy Academic Directorate",
                'sms_body' => 'Bariton Faculty: You were assigned to teach {{ course_title }}. Log in to your Faculty Studio to create modules.',
                'variables' => ['instructor_name', 'course_title', 'course_id', 'app_url'],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $data) {
            NotificationTemplate::updateOrCreate(
                ['key' => $data['key']],
                $data
            );
        }
    }
}
