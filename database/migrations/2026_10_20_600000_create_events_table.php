<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type')->default('bootcamp'); // bootcamp, workshop, masterclass
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('venue');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('training_fee_kes'); // minor units (cents)
            $table->unsignedInteger('training_fee_usd')->nullable();
            $table->boolean('has_accommodation')->default(false);
            $table->unsignedInteger('accommodation_fee_kes')->nullable()->default(0);
            $table->unsignedInteger('accommodation_fee_usd')->nullable()->default(0);
            $table->string('status')->default('published'); // draft, published, cancelled, completed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
