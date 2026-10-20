<?php

namespace App\Services\Receipts;

use App\Models\CreditNote;
use App\Models\RefundRequest;

interface CreditNoteIssuerInterface
{
    /**
     * Issue an official sequential numbered credit note inside a database transaction.
     */
    public function issue(RefundRequest $refundRequest): CreditNote;
}
