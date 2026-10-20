<?php

namespace App\Services\Receipts;

use App\Models\CreditNote;
use App\Models\RefundRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StandardPdfCreditNoteIssuer implements CreditNoteIssuerInterface
{
    /**
     * Issue an official sequential numbered credit note inside a transaction with row lock.
     */
    public function issue(RefundRequest $refundRequest): CreditNote
    {
        $order = $refundRequest->order;

        // Obtain sequential number from locked credit_note counter row
        $counter = DB::table('receipt_counters')
            ->where('key', 'credit_note')
            ->lockForUpdate()
            ->first();

        $nextNumber = ($counter ? $counter->last_number : 0) + 1;
        $prefix = $counter ? $counter->prefix : 'BMAS-CRN-';

        DB::table('receipt_counters')
            ->where('key', 'credit_note')
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => now(),
            ]);

        $creditNoteNumber = $prefix.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);

        $disk = config('filesystems.private_disk', 'local');
        $pdfPath = "credit_notes/{$creditNoteNumber}.pdf";

        // Calculate VAT adjustment if VAT applies
        $vatRate = (float) $order->vat_rate_percent;
        $vatKes = $vatRate > 0 ? (int) round($refundRequest->amount_kes * ($vatRate / (100 + $vatRate))) : 0;

        $creditNote = CreditNote::create([
            'uuid' => (string) Str::uuid(),
            'credit_note_number' => $creditNoteNumber,
            'refund_request_id' => $refundRequest->id,
            'order_id' => $order->id,
            'user_id' => $refundRequest->user_id,
            'amount_kes' => $refundRequest->amount_kes,
            'vat_kes' => $vatKes,
            'currency' => $refundRequest->currency ?: 'KES',
            'pdf_path' => $pdfPath,
            'pdf_disk' => $disk,
            'issued_at' => now(),
        ]);

        $creditNote->load(['order', 'refundRequest.orderItem', 'user']);

        // Generate PDF
        try {
            $pdf = Pdf::loadView('receipts.credit_note', ['creditNote' => $creditNote]);
            Storage::disk($disk)->put($pdfPath, $pdf->output());
        } catch (\Exception $e) {
            Log::warning('Credit Note PDF generation notice: '.$e->getMessage());
        }

        return $creditNote;
    }
}
