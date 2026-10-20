<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('credit_note_number')->unique(); // BMAS-CRN-000001
            $table->foreignId('refund_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_kes');
            $table->unsignedBigInteger('vat_kes')->default(0);
            $table->string('currency')->default('KES');
            $table->string('pdf_path')->nullable();
            $table->string('pdf_disk')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // Initialize credit note counter row in receipt_counters
        DB::table('receipt_counters')->updateOrInsert(
            ['key' => 'credit_note'],
            [
                'last_number' => 0,
                'prefix' => 'BMAS-CRN-',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        DB::table('receipt_counters')->where('key', 'credit_note')->delete();
    }
};
