<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_disk')->default('local');
            $table->string('status')->default('draft'); // draft, published, archived
            $table->string('bundle_discount_type')->default('none'); // percent, fixed_kes, none
            $table->unsignedInteger('bundle_discount_value')->default(0); // percent integer (e.g. 15 for 15%) or KES minor units (e.g. 200000 for KES 2,000)
            $table->boolean('requires_sequential_levels')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
