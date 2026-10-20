<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment on this gateway.
     */
    public function initiate(Order $order, array $params = []): PaymentResult;

    /**
     * Process an incoming callback or confirmation payload.
     */
    public function confirm(Payment $payment, array $payload = []): PaymentResult;

    /**
     * Query gateway directly for the current payment status.
     */
    public function status(Payment $payment): PaymentResult;

    /**
     * Process a refund on a successful payment.
     */
    public function refund(Payment $payment, int $amountKes, string $reason): RefundResult;
}
