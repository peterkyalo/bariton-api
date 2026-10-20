<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

class CashPaymentGateway implements PaymentGatewayInterface
{
    /**
     * Initiation of a cash payment holds the order pending school office visit.
     */
    public function initiate(Order $order, array $params = []): PaymentResult
    {
        $reference = 'CASH-REF-'.strtoupper(Str::random(8));

        return PaymentResult::pending(
            reference: $reference,
            message: 'Cash payment initiated. Present this order reference at the school finance office.',
            data: [
                'payer_name' => $params['payer_name'] ?? $order->user->name,
                'instructions' => 'Pay at Bariton Music Academy Finance Office with Order #'.$order->order_number,
            ]
        );
    }

    /**
     * Confirmation when finance staff records the cash payment.
     */
    public function confirm(Payment $payment, array $payload = []): PaymentResult
    {
        $reference = $payload['reference'] ?? 'CSH-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

        return PaymentResult::successful(
            reference: $reference,
            message: 'Cash payment confirmed and recorded by finance staff.',
            data: $payload
        );
    }

    public function status(Payment $payment): PaymentResult
    {
        if ($payment->isSuccessful()) {
            return PaymentResult::successful($payment->reference, 'Cash payment recorded.');
        }

        return PaymentResult::pending($payment->reference, 'Awaiting cash payment at finance office.');
    }

    public function refund(Payment $payment, int $amountKes, string $reason): RefundResult
    {
        $ref = 'CSH-REF-'.strtoupper(Str::random(6));

        return RefundResult::successful($ref, 'Cash refund recorded with reference '.$ref);
    }
}
