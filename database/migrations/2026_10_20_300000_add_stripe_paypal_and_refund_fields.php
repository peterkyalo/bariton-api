<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('refund_window_days')->default(14)->after('expires_at');
            $table->string('refund_status')->default('none')->after('refund_window_days'); // none, partial, full
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->index()->after('reference');
            $table->string('paypal_order_id')->nullable()->index()->after('stripe_payment_intent_id');
            $table->string('idempotency_key')->nullable()->index()->after('paypal_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refund_window_days', 'refund_status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['stripe_payment_intent_id', 'paypal_order_id', 'idempotency_key']);
        });
    }
};
