<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CreditNote;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\ExamAttempt;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\StudentTutoringPackage;
use App\Models\TutoringSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    /**
     * Resolve date range bounds.
     */
    protected function resolveDates(?string $from, ?string $to): array
    {
        $startDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $endDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();

        return [$startDate, $endDate];
    }

    /**
     * Admin Dashboard Metrics:
     * Revenue by method and item type, enrollments, exam pass rates, event occupancy and tutoring use.
     */
    public function getAdminMetrics(?string $from = null, ?string $to = null): array
    {
        [$startDate, $endDate] = $this->resolveDates($from, $to);

        // 1. Revenue by Method
        $revenueByMethod = Payment::whereIn('status', ['successful', 'completed'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('gateway as payment_method', DB::raw('SUM(amount_kes) as total_kes'), DB::raw('COUNT(*) as count'))
            ->groupBy('gateway')
            ->get()
            ->keyBy('payment_method')
            ->toArray();

        $totalRevenueKes = Payment::whereIn('status', ['successful', 'completed'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount_kes');

        // 2. Revenue by Item Type
        $revenueByItemType = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['paid', 'completed'])
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->select('order_items.item_type', DB::raw('SUM(order_items.price_kes) as total_kes'), DB::raw('COUNT(*) as count'))
            ->groupBy('order_items.item_type')
            ->get()
            ->keyBy('item_type')
            ->toArray();

        // 3. Enrollments Count & Validation Path
        $enrollmentsQuery = Enrollment::whereBetween('created_at', [$startDate, $endDate]);
        $totalEnrollments = $enrollmentsQuery->count();

        $pathBreakdown = Enrollment::whereBetween('created_at', [$startDate, $endDate])
            ->select('path_type', DB::raw('COUNT(*) as count'))
            ->groupBy('path_type')
            ->pluck('count', 'path_type')
            ->toArray();

        // 4. Exam Pass Rates
        $examAttemptsQuery = ExamAttempt::where('status', 'graded')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalGradedExams = $examAttemptsQuery->count();
        $passedExams = (clone $examAttemptsQuery)->where('passed', true)->count();
        $failedExams = $totalGradedExams - $passedExams;
        $passRate = $totalGradedExams > 0 ? round(($passedExams / $totalGradedExams) * 100, 1) : 0;

        // 5. Event Occupancy
        $events = Event::withCount(['bookings' => function ($q) {
            $q->whereIn('status', ['confirmed', 'attended']);
        }])->get();

        $totalCapacity = $events->sum('capacity');
        $totalOccupiedSeats = $events->sum('bookings_count');
        $occupancyRate = $totalCapacity > 0 ? round(($totalOccupiedSeats / $totalCapacity) * 100, 1) : 0;

        // 6. Tutoring Use
        $totalTutoringCreditsSold = StudentTutoringPackage::whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_credits');
        $tutoringSessionsQuery = TutoringSession::whereBetween('scheduled_start', [$startDate, $endDate]);
        $totalSessionsBooked = $tutoringSessionsQuery->count();
        $attendedSessions = (clone $tutoringSessionsQuery)->where('status', 'attended')->count();
        $missedSessions = (clone $tutoringSessionsQuery)->where('status', 'missed')->count();

        return [
            'period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'revenue' => [
                'total_kes' => (int) $totalRevenueKes,
                'total_usd' => round($totalRevenueKes / 13000, 2),
                'by_method' => $revenueByMethod,
                'by_item_type' => $revenueByItemType,
            ],
            'enrollments' => [
                'total' => $totalEnrollments,
                'standard' => $pathBreakdown['standard'] ?? 0,
                'assessment_only' => $pathBreakdown['assessment_only'] ?? 0,
            ],
            'exams' => [
                'total_graded' => $totalGradedExams,
                'passed' => $passedExams,
                'failed' => $failedExams,
                'pass_rate_percentage' => $passRate,
            ],
            'events' => [
                'total_events' => $events->count(),
                'total_capacity' => $totalCapacity,
                'occupied_seats' => $totalOccupiedSeats,
                'occupancy_percentage' => $occupancyRate,
            ],
            'tutoring' => [
                'credits_sold' => (int) $totalTutoringCreditsSold,
                'sessions_booked' => $totalSessionsBooked,
                'attended' => $attendedSessions,
                'missed' => $missedSessions,
            ],
        ];
    }

    /**
     * Finance Dashboard Metrics:
     * Payments, cash entries, pending refunds, credit notes and daily totals.
     */
    public function getFinanceMetrics(?string $from = null, ?string $to = null): array
    {
        [$startDate, $endDate] = $this->resolveDates($from, $to);

        // Payments Breakdown
        $payments = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->with(['order.user', 'receipt'])
            ->latest()
            ->take(20)
            ->get();

        $totalCompleted = Payment::whereIn('status', ['successful', 'completed'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount_kes');

        // Cash Entries
        $cashEntriesCount = Payment::where('gateway', 'cash')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $cashTotalKes = Payment::where('gateway', 'cash')
            ->whereIn('status', ['successful', 'completed'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount_kes');

        // Pending Refunds
        $pendingRefunds = RefundRequest::where('status', 'pending')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['order.user', 'user'])
            ->get();

        $pendingRefundsCount = $pendingRefunds->count();
        $pendingRefundsAmount = $pendingRefunds->sum('requested_amount_kes');

        // Credit Notes
        $creditNotes = CreditNote::whereBetween('created_at', [$startDate, $endDate])
            ->with('user')
            ->get();

        $creditNotesCount = $creditNotes->count();
        $creditNotesAmount = $creditNotes->sum('amount_kes');

        // Daily Totals (last 14 or date range)
        $dailyTotals = Payment::whereIn('status', ['successful', 'completed'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount_kes) as total_kes'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();

        return [
            'period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_revenue_kes' => (int) $totalCompleted,
                'cash_entries_count' => $cashEntriesCount,
                'cash_total_kes' => (int) $cashTotalKes,
                'pending_refunds_count' => $pendingRefundsCount,
                'pending_refunds_amount_kes' => (int) $pendingRefundsAmount,
                'credit_notes_count' => $creditNotesCount,
                'credit_notes_total_kes' => (int) $creditNotesAmount,
            ],
            'daily_totals' => $dailyTotals,
            'recent_payments' => $payments,
            'pending_refunds' => $pendingRefunds,
            'credit_notes' => $creditNotes,
        ];
    }

    /**
     * Instructor Dashboard Metrics:
     * Grading queue and upcoming sessions.
     */
    public function getInstructorMetrics(User $instructor): array
    {
        // 1. Assignment Grading Queue
        $pendingAssignments = AssignmentSubmission::where('status', 'submitted')
            ->whereHas('assignment.module.course.instructors', function ($q) use ($instructor) {
                $q->where('users.id', $instructor->id);
            })
            ->with(['assignment.module.course', 'student'])
            ->latest()
            ->get();

        // 2. Exam Submissions Queue
        $pendingExams = ExamAttempt::where('status', 'submitted')
            ->whereHas('exam.course.instructors', function ($q) use ($instructor) {
                $q->where('users.id', $instructor->id);
            })
            ->with(['exam.course', 'user'])
            ->latest()
            ->get();

        // 3. Upcoming Tutoring Sessions
        $upcomingSessions = TutoringSession::where('instructor_id', $instructor->id)
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->where('scheduled_start', '>=', now())
            ->with(['student', 'course'])
            ->orderBy('scheduled_start', 'asc')
            ->take(10)
            ->get();

        // 4. Course Roster Count with Modules Count
        $courses = Course::whereHas('instructors', function ($q) use ($instructor) {
            $q->where('users.id', $instructor->id);
        })->withCount(['enrollments', 'modules'])->latest()->get();

        $newlyAssignedCourses = $courses->filter(function ($c) {
            return $c->modules_count === 0 || ($c->created_at && $c->created_at->diffInDays(now()) <= 7);
        })->values();

        return [
            'pending_assignments_count' => $pendingAssignments->count(),
            'pending_assignments' => $pendingAssignments,
            'pending_exams_count' => $pendingExams->count(),
            'pending_exams' => $pendingExams,
            'upcoming_sessions_count' => $upcomingSessions->count(),
            'upcoming_sessions' => $upcomingSessions,
            'courses' => $courses,
            'newly_assigned_courses' => $newlyAssignedCourses,
        ];
    }

    /**
     * Coordinator Dashboard Metrics:
     * Upcoming events and attendance check-in roster.
     */
    public function getCoordinatorMetrics(): array
    {
        $events = Event::with(['bookings.user'])
            ->withCount([
                'bookings as confirmed_count' => fn ($q) => $q->whereIn('status', ['confirmed', 'attended']),
                'bookings as attended_count' => fn ($q) => $q->where('status', 'attended'),
                'bookings as accommodation_count' => fn ($q) => $q->where('booking_type', 'training_and_accommodation')->whereIn('status', ['confirmed', 'attended']),
            ])
            ->orderBy('start_date', 'asc')
            ->get();

        $totalAttendeesCheckedIn = EventBooking::where('status', 'attended')->count();
        $totalActiveBookings = EventBooking::whereIn('status', ['confirmed', 'attended'])->count();

        return [
            'events_count' => $events->count(),
            'total_attendees_checked_in' => $totalAttendeesCheckedIn,
            'total_active_bookings' => $totalActiveBookings,
            'events' => $events,
        ];
    }

    /**
     * Generate Admin Metrics CSV content
     */
    public function exportAdminCsv(?string $from = null, ?string $to = null): string
    {
        $metrics = $this->getAdminMetrics($from, $to);

        $out = fopen('php://memory', 'w');
        fputcsv($out, ['Bariton Music Academy - Executive Admin Report']);
        fputcsv($out, ['Period From', $metrics['period']['from'], 'Period To', $metrics['period']['to']]);
        fputcsv($out, []);

        // Revenue by Method
        fputcsv($out, ['--- REVENUE BY PAYMENT METHOD ---']);
        fputcsv($out, ['Payment Method', 'Transactions Count', 'Total (KES)', 'Total (USD)']);
        foreach ($metrics['revenue']['by_method'] as $method => $data) {
            $kes = $data['total_kes'] / 100;
            $usd = round($kes / 130, 2);
            fputcsv($out, [strtoupper($method), $data['count'], $kes, $usd]);
        }
        fputcsv($out, ['Total Revenue', '', $metrics['revenue']['total_kes'] / 100, $metrics['revenue']['total_usd']]);
        fputcsv($out, []);

        // Revenue by Item Type
        fputcsv($out, ['--- REVENUE BY ITEM TYPE ---']);
        fputcsv($out, ['Item Type', 'Items Sold', 'Total (KES)']);
        foreach ($metrics['revenue']['by_item_type'] as $type => $data) {
            fputcsv($out, [ucwords(str_replace('_', ' ', $type)), $data['count'], $data['total_kes'] / 100]);
        }
        fputcsv($out, []);

        // Academic & Operational KPIs
        fputcsv($out, ['--- KEY PERFORMANCE INDICATORS ---']);
        fputcsv($out, ['Metric', 'Value']);
        fputcsv($out, ['Total Course Enrollments', $metrics['enrollments']['total']]);
        fputcsv($out, ['Standard Curriculum Enrollments', $metrics['enrollments']['standard']]);
        fputcsv($out, ['Assessment-Only Validation Enrollments', $metrics['enrollments']['assessment_only']]);
        fputcsv($out, ['Graded Exam Attempts', $metrics['exams']['total_graded']]);
        fputcsv($out, ['Exam Pass Rate %', $metrics['exams']['pass_rate_percentage'].'%']);
        fputcsv($out, ['Physical Event Capacity', $metrics['events']['total_capacity']]);
        fputcsv($out, ['Occupied Event Seats', $metrics['events']['occupied_seats']]);
        fputcsv($out, ['Event Occupancy Rate %', $metrics['events']['occupancy_percentage'].'%']);
        fputcsv($out, ['Tutoring Credits Purchased', $metrics['tutoring']['credits_sold']]);
        fputcsv($out, ['Tutoring Sessions Booked', $metrics['tutoring']['sessions_booked']]);
        fputcsv($out, ['Attended Tutoring Sessions', $metrics['tutoring']['attended']]);

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Generate Finance Report CSV content
     */
    public function exportFinanceCsv(?string $from = null, ?string $to = null): string
    {
        [$startDate, $endDate] = $this->resolveDates($from, $to);

        $payments = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->with(['order.user', 'receipt'])
            ->orderBy('created_at', 'desc')
            ->get();

        $out = fopen('php://memory', 'w');
        fputcsv($out, ['Bariton Music Academy - Finance & Payments Ledger']);
        fputcsv($out, ['Period From', $startDate->toDateString(), 'Period To', $endDate->toDateString()]);
        fputcsv($out, []);
        fputcsv($out, ['Payment Reference', 'Receipt #', 'Date & Time', 'Student Name', 'Method', 'Amount (KES)', 'Status']);

        foreach ($payments as $p) {
            fputcsv($out, [
                $p->reference ?? $p->provider_payment_id ?? $p->order_id,
                $p->receipt ? $p->receipt->receipt_number : 'N/A',
                $p->created_at->toDateTimeString(),
                $p->order && $p->order->user ? $p->order->user->name : 'Unknown',
                strtoupper($p->gateway ?? $p->payment_method ?? 'UNKNOWN'),
                $p->amount_kes / 100,
                strtoupper($p->status),
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
