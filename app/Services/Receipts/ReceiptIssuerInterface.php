<?php

namespace App\Services\Receipts;

use App\Models\Payment;
use App\Models\Receipt;

interface ReceiptIssuerInterface
{
    /**
     * Issue an official sequential numbered receipt for a successful payment.
     */
    public function issue(Payment $payment): Receipt;
}
