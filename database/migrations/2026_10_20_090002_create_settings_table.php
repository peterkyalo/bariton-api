<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('type', 32)->default('string'); // string, integer, float, boolean, json
            $table->string('group', 32)->index(); // uploads, tutoring, assessment, refunds, payments, bookings, currency, tax
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
