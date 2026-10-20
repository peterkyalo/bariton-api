<?php

namespace App\Services;

use App\Models\InstructorAvailability;
use App\Models\InstructorBlockedDate;
use App\Models\Setting;
use App\Models\TutoringSession;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class TutoringSlotService
{
    /**
     * Compute open tutoring slots for an instructor within a date range in Africa/Nairobi timezone.
     */
    public function getAvailableSlots(
        User $instructor,
        string|Carbon $startDate,
        string|Carbon $endDate
    ): array {
        $tz = Setting::getValue('tutoring_timezone', 'Africa/Nairobi');
        $slotLength = (int) Setting::getValue('tutoring_slot_length_minutes', 60);
        $buffer = (int) Setting::getValue('tutoring_buffer_minutes', 15);

        $start = Carbon::parse($startDate, $tz)->startOfDay();
        $end = Carbon::parse($endDate, $tz)->endOfDay();
        $now = Carbon::now($tz);

        // Fetch recurring weekly availabilities for this instructor
        $availabilities = InstructorAvailability::where('instructor_id', $instructor->id)
            ->where('is_active', true)
            ->get()
            ->groupBy('day_of_week');

        // Fetch blocked dates (time-off/blackouts)
        $blockedDates = InstructorBlockedDate::where('instructor_id', $instructor->id)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($sub) use ($start, $end) {
                        $sub->where('start_date', '<=', $start->toDateString())
                            ->where('end_date', '>=', $end->toDateString());
                    });
            })
            ->get();

        // Fetch existing booked sessions in this window
        $existingSessions = TutoringSession::where('instructor_id', $instructor->id)
            ->whereIn('status', ['scheduled', 'attended'])
            ->whereBetween('scheduled_start', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->get();

        $openSlots = [];
        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            $dateStr = $date->toDateString();

            // Check if date is blocked
            $isBlocked = $blockedDates->contains(function ($blocked) use ($dateStr) {
                return $dateStr >= $blocked->start_date->toDateString() && $dateStr <= $blocked->end_date->toDateString();
            });

            if ($isBlocked) {
                continue;
            }

            $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
            if (! isset($availabilities[$dayOfWeek])) {
                continue;
            }

            foreach ($availabilities[$dayOfWeek] as $avail) {
                $shiftStart = Carbon::parse("{$dateStr} {$avail->start_time}", $tz);
                $shiftEnd = Carbon::parse("{$dateStr} {$avail->end_time}", $tz);

                $currentSlotStart = $shiftStart->copy();

                while ($currentSlotStart->copy()->addMinutes($slotLength)->lte($shiftEnd)) {
                    $currentSlotEnd = $currentSlotStart->copy()->addMinutes($slotLength);

                    // Must be in the future
                    if ($currentSlotStart->gt($now)) {
                        // Check overlap with any existing booked session
                        $hasOverlap = $existingSessions->contains(function ($session) use ($currentSlotStart, $currentSlotEnd, $tz) {
                            $sessStart = Carbon::parse($session->scheduled_start, $tz);
                            $sessEnd = Carbon::parse($session->scheduled_end, $tz);

                            return $currentSlotStart->lt($sessEnd) && $currentSlotEnd->gt($sessStart);
                        });

                        if (! $hasOverlap) {
                            $openSlots[] = [
                                'instructor_id' => $instructor->id,
                                'instructor_name' => $instructor->name,
                                'date' => $dateStr,
                                'start' => $currentSlotStart->toIso8601String(),
                                'end' => $currentSlotEnd->toIso8601String(),
                                'start_time' => $currentSlotStart->format('H:i'),
                                'end_time' => $currentSlotEnd->format('H:i'),
                                'duration_minutes' => $slotLength,
                            ];
                        }
                    }

                    // Step forward by slot length + buffer
                    $currentSlotStart->addMinutes($slotLength + $buffer);
                }
            }
        }

        return $openSlots;
    }
}
