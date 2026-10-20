<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_open_for_validation')->default(false)->after('requires_sequential_levels');
            $table->unsignedInteger('validation_price_kes')->nullable()->after('is_open_for_validation');
            $table->unsignedInteger('validation_price_usd')->nullable()->after('validation_price_kes');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['is_open_for_validation', 'validation_price_kes', 'validation_price_usd']);
        });
    }
};
