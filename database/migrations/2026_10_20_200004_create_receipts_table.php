<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('receipt_number')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_kes');
            $table->unsignedBigInteger('vat_kes')->default(0);
            $table->string('currency')->default('KES');
            $table->string('pdf_path')->nullable();
            $table->string('pdf_disk')->default('local');
            $table->string('status')->default('issued'); // issued, void
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
