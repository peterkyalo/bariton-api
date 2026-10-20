<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_counters', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->string('prefix')->default('BMAS-RCT-');
            $table->timestamps();
        });

        // Initialize default main counter
        DB::table('receipt_counters')->insert([
            'key' => 'main',
            'last_number' => 0,
            'prefix' => 'BMAS-RCT-',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_counters');
    }
};
