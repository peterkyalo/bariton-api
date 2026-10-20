<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('booking_type')->default('training_only'); // training_only, training_and_accommodation
            $table->unsignedInteger('training_fee_kes');
            $table->unsignedInteger('accommodation_fee_kes')->default(0);
            $table->unsignedInteger('total_fee_kes');
            $table->string('status')->default('held'); // held, confirmed, unseated, cancelled, refunded, attended, no_show
            $table->string('hold_type')->default('online'); // online, cash
            $table->timestamp('hold_expires_at')->nullable();
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete(); // staff who created cash hold
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unseated_at')->nullable();
            $table->text('unseated_reason')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('hold_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_bookings');
    }
};
