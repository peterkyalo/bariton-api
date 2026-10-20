<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\Payments\PaymentManager;
use App\Services\Receipts\CreditNoteIssuerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RefundService
{
    public function __construct(
        protected PaymentManager $paymentManager,
        protected SettingsService $settingsService,
        protected CreditNoteIssuerInterface $creditNoteIssuer,
        protected CourseProgressService $progressService
    ) {}

    /**
     * Validate refund eligibility for an order or order item.
     */
    public function validateEligibility(Order $order, ?OrderItem $item = null, ?User $user = null): array
    {
        $user = $user ?: $order->user;

        // 1. Must be paid
        if (! $order->isPaid()) {
            return [
                'eligible' => false,
                'message' => 'Only paid orders are eligible for a refund.',
            ];
        }

        // 2. Refund window check (default 14 days)
        $windowDays = $order->refund_window_days ?: (int) $this->settingsService->get('refund_window_days', 14);
        if ($order->paid_at && $order->paid_at->diffInDays(now()) > $windowDays) {
            return [
                'eligible' => false,
                'message' => "The {$windowDays}-day refund window for this order has expired.",
            ];
        }

        // 3. Find related course id
        $courseId = null;
        if ($item) {
            if ($item->item_type === 'course_bundle' || $item->item_type === 'bundle_upgrade') {
                $courseId = $item->item_id;
            } elseif ($item->item_type === 'module') {
                $courseId = $item->meta['course_id'] ?? null;
            }
        } else {
            // First item course
            $first = $order->items->first();
            $courseId = $first?->item_type === 'module' ? ($first->meta['course_id'] ?? null) : $first?->item_id;
        }

        // 4. Block if certificate already issued for this course
        if ($courseId && Schema::hasTable('certificates')) {
            $hasCertificate = DB::table('certificates')
                ->where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->where('status', '!=', 'revoked')
                ->exists();

            if ($hasCertificate) {
                return [
                    'eligible' => false,
                    'message' => 'Refunds are blocked because an official completion certificate has already been issued for this course.',
                ];
            }
        }

        // 5. Block if course progress exceeds limit (default 50%)
        $maxProgress = (int) $this->settingsService->get('refund_max_course_progress_percent', 50);
        if ($courseId) {
            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->first();

            if ($enrollment && $enrollment->progress_percent >= $maxProgress) {
                return [
                    'eligible' => false,
                    'message' => "Refunds are not permitted once course progress reaches or exceeds {$maxProgress}%.",
                ];
            }
        }

        return ['eligible' => true];
    }

    /**
     * Submit a student refund request.
     */
    public function requestRefund(Order $order, ?int $orderItemId, string $reason, User $user): RefundRequest
    {
        $item = $orderItemId ? $order->items()->findOrFail($orderItemId) : null;

        $check = $this->validateEligibility($order, $item, $user);
        if (! $check['eligible']) {
            throw new InvalidArgumentException($check['message']);
        }

        // Check if an existing pending or approved request exists
        $existing = RefundRequest::where('order_id', $order->id)
            ->where(function ($q) use ($orderItemId) {
                if ($orderItemId) {
                    $q->where('order_item_id', $orderItemId);
                }
            })
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('A refund request has already been submitted for this item or order.');
        }

        $amountKes = $item ? $item->price_kes : $order->total_kes;
        $exchangeRate = $order->exchange_rate_used ?: 130.00;
        $amountUsdCents = (int) ceil(($amountKes / $exchangeRate));

        return RefundRequest::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'order_item_id' => $item?->id,
            'user_id' => $user->id,
            'amount_kes' => $amountKes,
            'amount_usd_cents' => $amountUsdCents,
            'currency' => $order->currency ?: 'KES',
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    /**
     * Approve refund request, call payment driver, revoke access, and issue Credit Note.
     */
    public function approve(RefundRequest $refundRequest, User $reviewer, ?string $manualReference = null): CreditNote
    {
        return DB::transaction(function () use ($refundRequest, $reviewer, $manualReference) {
            $order = $refundRequest->order;
            $payment = $order->successfulPayment ?: $order->payments()->latest()->first();

            $refundRef = null;

            // Process gateway refund
            if ($payment) {
                if (in_array($payment->gateway, ['cash', 'mpesa'])) {
                    // Manual reference for Cash and M-Pesa recorded by finance
                    $refundRef = $manualReference ?: 'MAN-REF-'.strtoupper(Str::random(10));
                } else {
                    $gateway = $this->paymentManager->gateway($payment->gateway);
                    $result = $gateway->refund($payment, $refundRequest->amount_kes, $refundRequest->reason);
                    if (! $result->success) {
                        throw new \RuntimeException($result->message);
                    }
                    $refundRef = $result->reference;
                }
            } else {
                $refundRef = $manualReference ?: 'REF-'.strtoupper(Str::random(10));
            }

            // Revoke access to refunded items
            $this->revokeAccess($refundRequest);

            // Update RefundRequest
            $refundRequest->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'refund_reference' => $refundRef,
            ]);

            // Update Order refund status
            $totalRefunded = $order->refundRequests()->where('status', 'approved')->sum('amount_kes');
            $refundStatus = ($totalRefunded >= $order->total_kes) ? 'full' : 'partial';
            $order->update(['refund_status' => $refundStatus]);

            // Issue official numbered Credit Note
            return $this->creditNoteIssuer->issue($refundRequest);
        });
    }

    /**
     * Reject refund request.
     */
    public function reject(RefundRequest $refundRequest, User $reviewer, string $notes): void
    {
        $refundRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Revoke access for the refunded item(s) only.
     */
    protected function revokeAccess(RefundRequest $refundRequest): void
    {
        $order = $refundRequest->order;
        $user = $refundRequest->user;
        $item = $refundRequest->orderItem;

        if ($item) {
            if ($item->item_type === 'module') {
                $moduleId = $item->item_id;
                EnrollmentModule::whereHas('enrollment', fn ($q) => $q->where('user_id', $user->id))
                    ->where('module_id', $moduleId)
                    ->delete();

                // Recalculate enrollment progress
                $courseId = $item->meta['course_id'] ?? null;
                if ($courseId) {
                    $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $courseId)->first();
                    if ($enrollment) {
                        $this->progressService->calculateProgress($enrollment);
                    }
                }
            } elseif ($item->item_type === 'course_bundle' || $item->item_type === 'bundle_upgrade') {
                $courseId = $item->item_id;
                $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $courseId)->first();
                if ($enrollment) {
                    $enrollment->modules()->delete();
                    $enrollment->update(['status' => 'cancelled', 'progress_percent' => 0.00]);
                }
            }
        } else {
            // Full order refund: revoke access to all items in order
            foreach ($order->items as $orderItem) {
                if ($orderItem->item_type === 'module') {
                    EnrollmentModule::whereHas('enrollment', fn ($q) => $q->where('user_id', $user->id))
                        ->where('module_id', $orderItem->item_id)
                        ->delete();
                } elseif ($orderItem->item_type === 'course_bundle' || $orderItem->item_type === 'bundle_upgrade') {
                    $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $orderItem->item_id)->first();
                    if ($enrollment) {
                        $enrollment->modules()->delete();
                        $enrollment->update(['status' => 'cancelled', 'progress_percent' => 0.00]);
                    }
                }
            }
        }
    }
}
