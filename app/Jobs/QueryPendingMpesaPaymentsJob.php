<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\OrderFulfillmentService;
use App\Services\Payments\MpesaPaymentGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class QueryPendingMpesaPaymentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        MpesaPaymentGateway $mpesaGateway,
        OrderFulfillmentService $fulfillmentService
    ): void {
        $pendingPayments = Payment::query()
            ->where('gateway', 'mpesa')
            ->where('status', 'pending')
            ->whereNotNull('checkout_request_id')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->with(['order.items', 'order.user'])
            ->get();

        foreach ($pendingPayments as $payment) {
            try {
                $result = $mpesaGateway->status($payment);

                if ($result->status === 'successful') {
                    $payment->reference = $result->reference ?: $payment->reference;
                    $fulfillmentService->fulfill($payment->order, $payment);
                    Log::info("M-Pesa payment {$payment->id} query confirmed and fulfilled.");
                } elseif ($result->status === 'failed') {
                    $payment->update([
                        'status' => 'failed',
                        'payload' => array_merge($payment->payload ?? [], ['query_failure' => $result->message]),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("M-Pesa status query job error for payment {$payment->id}: ".$e->getMessage());
            }
        }
    }
}
