<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, paid, cancelled, expired
            $table->unsignedBigInteger('total_kes'); // minor units
            $table->unsignedBigInteger('vat_kes')->default(0); // minor units
            $table->decimal('vat_rate_percent', 5, 2)->default(0.00); // snapshot from settings
            $table->decimal('exchange_rate_used', 8, 2)->default(130.00); // snapshot from settings
            $table->unsignedBigInteger('total_usd_cents'); // snapshot USD cents
            $table->string('currency')->default('KES');
            $table->string('payment_method')->nullable(); // mpesa, cash, stripe, paypal
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
