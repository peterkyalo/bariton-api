<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway'); // mpesa, cash, stripe, paypal
            $table->unsignedBigInteger('amount_kes'); // minor units
            $table->string('status')->default('pending'); // pending, successful, failed
            $table->string('reference')->nullable()->unique(); // external transaction ref or M-Pesa receipt
            $table->string('checkout_request_id')->nullable()->index(); // Daraja STK Push request id
            $table->string('merchant_request_id')->nullable()->index();
            $table->string('payer_phone')->nullable();
            $table->string('payer_name')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
