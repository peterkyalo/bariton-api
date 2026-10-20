<?php

namespace App\Services\Receipts;

use App\Models\Payment;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StandardPdfReceiptIssuer implements ReceiptIssuerInterface
{
    /**
     * Issue an official sequential numbered receipt inside a transaction with row lock.
     */
    public function issue(Payment $payment): Receipt
    {
        $order = $payment->order;

        // Obtain sequential number from locked counter row
        $counter = DB::table('receipt_counters')
            ->where('key', 'main')
            ->lockForUpdate()
            ->first();

        $nextNumber = ($counter ? $counter->last_number : 0) + 1;
        $prefix = $counter ? $counter->prefix : 'BMAS-RCT-';

        DB::table('receipt_counters')
            ->where('key', 'main')
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => now(),
            ]);

        $receiptNumber = $prefix.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);

        $disk = config('filesystems.private_disk', 'local');
        $pdfPath = "receipts/{$receiptNumber}.pdf";

        // Create receipt model
        $receipt = Receipt::create([
            'uuid' => (string) Str::uuid(),
            'receipt_number' => $receiptNumber,
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'user_id' => $order->user_id,
            'amount_kes' => $payment->amount_kes,
            'vat_kes' => $order->vat_kes,
            'currency' => $order->currency ?: 'KES',
            'pdf_path' => $pdfPath,
            'pdf_disk' => $disk,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $receipt->load(['order.items', 'user', 'payment']);

        // Generate PDF
        try {
            $pdf = Pdf::loadView('receipts.pdf', ['receipt' => $receipt]);
            Storage::disk($disk)->put($pdfPath, $pdf->output());
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Receipt PDF generation notice: '.$e->getMessage());
        }

        return $receipt;
    }
}
