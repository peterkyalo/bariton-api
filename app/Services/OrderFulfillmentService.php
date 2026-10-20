<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Module;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Receipts\ReceiptIssuerInterface;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    public function __construct(
        protected ReceiptIssuerInterface $receiptIssuer
    ) {}

    /**
     * Fulfill an order upon confirmed payment inside a database transaction.
     */
    public function fulfill(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order, $payment) {
            // 1. Mark order and payment as paid
            $order->update([
                'status' => 'paid',
                'payment_method' => $payment->gateway,
                'paid_at' => now(),
            ]);

            $payment->update([
                'status' => 'successful',
                'paid_at' => now(),
            ]);

            // 2. Grant access to purchased items
            foreach ($order->items as $item) {
                $this->grantAccessForItem($order, $item);
            }

            // 3. Issue sequential numbered receipt
            $this->receiptIssuer->issue($payment);
        });
    }

    /**
     * Grant enrollment and module access based on item type.
     */
    protected function grantAccessForItem(Order $order, $item): void
    {
        $user = $order->user;

        if ($item->item_type === 'course_bundle') {
            $course = Course::with('modules')->find($item->item_id);
            if (! $course) {
                return;
            }

            $enrollment = Enrollment::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ],
                [
                    'path_type' => 'standard',
                    'status' => 'active',
                    'progress_percent' => 0.00,
                ]
            );

            // Unlock all modules in this course bundle
            foreach ($course->modules as $mod) {
                EnrollmentModule::firstOrCreate(
                    [
                        'enrollment_id' => $enrollment->id,
                        'module_id' => $mod->id,
                    ],
                    [
                        'amount_paid_kes' => $mod->price_kes,
                        'unlocked_at' => now(),
                    ]
                );
            }
        } elseif ($item->item_type === 'module') {
            $module = Module::with('course')->find($item->item_id);
            if (! $module || ! $module->course) {
                return;
            }

            $enrollment = Enrollment::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'course_id' => $module->course_id,
                ],
                [
                    'path_type' => 'standard',
                    'status' => 'active',
                    'progress_percent' => 0.00,
                ]
            );

            EnrollmentModule::firstOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'module_id' => $module->id,
                ],
                [
                    'amount_paid_kes' => $item->price_kes,
                    'unlocked_at' => now(),
                ]
            );
        } elseif ($item->item_type === 'bundle_upgrade') {
            $course = Course::with('modules')->find($item->item_id);
            if (! $course) {
                return;
            }

            $enrollment = Enrollment::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ],
                [
                    'path_type' => 'standard',
                    'status' => 'active',
                ]
            );

            // Unlock any remaining modules for this course
            foreach ($course->modules as $mod) {
                EnrollmentModule::firstOrCreate(
                    [
                        'enrollment_id' => $enrollment->id,
                        'module_id' => $mod->id,
                    ],
                    [
                        'amount_paid_kes' => $mod->price_kes,
                        'unlocked_at' => now(),
                    ]
                );
            }
        } elseif ($item->item_type === 'exam_retake_package') {
            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('course_id', $item->item_id)
                ->first();

            if ($enrollment) {
                // Grant an additional 3 attempts
                $enrollment->increment('exam_attempt_limit', 3);

                activity()
                    ->performedOn($enrollment)
                    ->causedBy($user)
                    ->withProperties(['order_id' => $order->id, 'added_attempts' => 3])
                    ->log('Exam retake package activated (+3 attempts)');
            }
        }
    }
}
