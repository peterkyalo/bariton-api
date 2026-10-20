<?php

namespace App\Services;

use App\Models\Course;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\StudentTutoringPackage;
use App\Models\TutoringSession;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TutoringBookingService
{
    /**
     * Book a 1-on-1 tutoring session using 1 session credit from the student's package.
     * Prevents double-booking via database transaction and row-level locking.
     *
     * @throws Exception
     */
    public function bookSession(
        User $student,
        StudentTutoringPackage $studentPackage,
        User $instructor,
        Carbon|string $scheduledStart,
        string $mode = 'zoom',
        ?string $meetingLink = null,
        ?string $location = null,
        ?Course $course = null
    ): TutoringSession {
        return DB::transaction(function () use (
            $student, $studentPackage, $instructor, $scheduledStart, $mode, $meetingLink, $location, $course
        ) {
            $tz = Setting::getValue('tutoring_timezone', 'Africa/Nairobi');
            $start = Carbon::parse($scheduledStart, $tz);
            $slotLength = (int) Setting::getValue('tutoring_slot_length_minutes', 60);
            $end = $start->copy()->addMinutes($slotLength);

            // 1. Lock and verify student tutoring package credits
            $lockedPackage = StudentTutoringPackage::where('id', $studentPackage->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPackage->user_id !== $student->id) {
                throw new InvalidArgumentException('You do not own this tutoring package.');
            }

            if (! $lockedPackage->hasAvailableCredits()) {
                if ($lockedPackage->isExpired()) {
                    throw new Exception('This tutoring package has expired. Please purchase a new package.');
                }
                throw new Exception('No remaining tutoring session credits available in this package.');
            }

            // 2. Concurrency lock against instructor scheduled sessions to prevent double booking
            $overlap = TutoringSession::where('instructor_id', $instructor->id)
                ->whereIn('status', ['scheduled', 'attended'])
                ->where(function ($query) use ($start, $end) {
                    $query->where('scheduled_start', '<', $end->toDateTimeString())
                        ->where('scheduled_end', '>', $start->toDateTimeString());
                })
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw new Exception('This time slot is no longer available. Another student just booked this slot.');
            }

            // 3. Deduct credit
            $lockedPackage->decrement('remaining_credits');
            $lockedPackage->increment('used_credits');

            if ($lockedPackage->remaining_credits <= 0) {
                $lockedPackage->update(['status' => 'exhausted']);
            }

            // 4. Create booked session
            $session = TutoringSession::create([
                'student_tutoring_package_id' => $lockedPackage->id,
                'student_id' => $student->id,
                'instructor_id' => $instructor->id,
                'course_id' => $course?->id ?: $lockedPackage->course_id,
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'mode' => $mode,
                'meeting_link' => $meetingLink,
                'location' => $location,
                'status' => 'scheduled',
            ]);

            // 5. Activity log
            activity()
                ->performedOn($session)
                ->causedBy($student)
                ->withProperties([
                    'instructor_id' => $instructor->id,
                    'scheduled_start' => $start->toISOString(),
                    'remaining_credits' => $lockedPackage->remaining_credits,
                ])
                ->log('Tutoring session booked');

            // 6. Dispatch Notifications
            NotificationLog::create([
                'notifiable_type' => User::class,
                'notifiable_id' => $student->id,
                'channel' => 'mail',
                'notification_type' => 'tutoring_session_booked_student',
                'recipient' => $student->email,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            if (! empty($student->phone)) {
                NotificationLog::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $student->id,
                    'channel' => 'sms',
                    'notification_type' => 'tutoring_session_booked_student_sms',
                    'recipient' => $student->phone,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }

            NotificationLog::create([
                'notifiable_type' => User::class,
                'notifiable_id' => $instructor->id,
                'channel' => 'mail',
                'notification_type' => 'tutoring_session_booked_instructor',
                'recipient' => $instructor->email,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $session;
        });
    }

    /**
     * Reschedule a session with advance notice check or emergency waiver.
     *
     * @throws Exception
     */
    public function rescheduleSession(
        TutoringSession $session,
        User $requester,
        Carbon|string $newScheduledStart,
        ?string $newMode = null,
        bool $waiveNotice = false,
        ?string $waiverReason = null
    ): TutoringSession {
        return DB::transaction(function () use (
            $session, $requester, $newScheduledStart, $newMode, $waiveNotice, $waiverReason
        ) {
            $lockedSession = TutoringSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            if ($lockedSession->status !== 'scheduled') {
                throw new Exception("Cannot reschedule session with status '{$lockedSession->status}'.");
            }

            $tz = Setting::getValue('tutoring_timezone', 'Africa/Nairobi');
            $noticeHours = (int) Setting::getValue('reschedule_notice_hours', 24);
            $hoursUntilCurrent = now($tz)->diffInHours($lockedSession->scheduled_start, false);

            if ($hoursUntilCurrent < $noticeHours && ! $waiveNotice) {
                throw new Exception("Rescheduling requires at least {$noticeHours} hours advance notice. In unavoidable circumstances (e.g. medical illness), submit with a waiver reason.");
            }

            $newStart = Carbon::parse($newScheduledStart, $tz);
            $slotLength = (int) Setting::getValue('tutoring_slot_length_minutes', 60);
            $newEnd = $newStart->copy()->addMinutes($slotLength);

            // Check overlap for instructor at new time
            $overlap = TutoringSession::where('instructor_id', $lockedSession->instructor_id)
                ->where('id', '!=', $lockedSession->id)
                ->whereIn('status', ['scheduled', 'attended'])
                ->where(function ($query) use ($newStart, $newEnd) {
                    $query->where('scheduled_start', '<', $newEnd->toDateTimeString())
                        ->where('scheduled_end', '>', $newStart->toDateTimeString());
                })
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw new Exception('The requested new time slot is not available.');
            }

            $lockedSession->update([
                'scheduled_start' => $newStart,
                'scheduled_end' => $newEnd,
                'mode' => $newMode ?: $lockedSession->mode,
                'notice_waived' => $waiveNotice,
                'waiver_reason' => $waiveNotice ? $waiverReason : null,
            ]);

            activity()
                ->performedOn($lockedSession)
                ->causedBy($requester)
                ->withProperties([
                    'new_start' => $newStart->toISOString(),
                    'notice_waived' => $waiveNotice,
                    'waiver_reason' => $waiverReason,
                ])
                ->log('Tutoring session rescheduled');

            return $lockedSession;
        });
    }

    /**
     * Cancel session by instructor and return credit to the student.
     */
    public function cancelByInstructor(TutoringSession $session, User $instructor, string $reason): TutoringSession
    {
        return DB::transaction(function () use ($session, $instructor, $reason) {
            $lockedSession = TutoringSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            if ($lockedSession->status !== 'scheduled') {
                throw new Exception("Cannot cancel session with status '{$lockedSession->status}'.");
            }

            // Return 1 session credit to the student's package
            $package = StudentTutoringPackage::where('id', $lockedSession->student_tutoring_package_id)
                ->lockForUpdate()
                ->firstOrFail();

            $package->increment('remaining_credits');
            $package->decrement('used_credits');

            if ($package->status === 'exhausted') {
                $package->update(['status' => 'active']);
            }

            $lockedSession->update([
                'status' => 'cancelled_by_instructor',
                'cancellation_reason' => $reason,
            ]);

            activity()
                ->performedOn($lockedSession)
                ->causedBy($instructor)
                ->withProperties(['reason' => $reason, 'credit_returned' => true])
                ->log('Tutoring session cancelled by instructor; credit restored to student');

            // Dispatch urgent alert to student
            NotificationLog::create([
                'notifiable_type' => User::class,
                'notifiable_id' => $lockedSession->student_id,
                'channel' => 'mail',
                'notification_type' => 'tutoring_session_cancelled_by_instructor',
                'recipient' => $lockedSession->student->email,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            if (! empty($lockedSession->student->phone)) {
                NotificationLog::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $lockedSession->student_id,
                    'channel' => 'sms',
                    'notification_type' => 'tutoring_session_cancelled_by_instructor_sms',
                    'recipient' => $lockedSession->student->phone,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }

            return $lockedSession;
        });
    }

    /**
     * Mark session attendance (attended or missed).
     */
    public function markAttendance(TutoringSession $session, User $instructor, string $status, ?string $notes = null): TutoringSession
    {
        if (! in_array($status, ['attended', 'missed'])) {
            throw new InvalidArgumentException("Attendance status must be 'attended' or 'missed'.");
        }

        $session->update([
            'status' => $status,
            'instructor_notes' => $notes,
        ]);

        activity()
            ->performedOn($session)
            ->causedBy($instructor)
            ->withProperties(['status' => $status, 'notes' => $notes])
            ->log("Tutoring session marked as {$status}");

        return $session;
    }
}
