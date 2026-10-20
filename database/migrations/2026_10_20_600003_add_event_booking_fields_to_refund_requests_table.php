<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreignId('event_booking_id')->nullable()->after('order_item_id')->constrained('event_bookings')->nullOnDelete();
            $table->string('proof_document_path')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropForeign(['event_booking_id']);
            $table->dropColumn(['event_booking_id', 'proof_document_path']);
        });
    }
};
