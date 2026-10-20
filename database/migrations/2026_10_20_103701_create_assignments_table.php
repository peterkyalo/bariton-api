<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rubric_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('instructions');
            $table->unsignedSmallInteger('order')->default(1);
            $table->string('submission_type')->default('video'); // audio, video, both
            $table->json('accepted_formats')->nullable();
            $table->unsignedSmallInteger('max_file_size_mb')->default(200);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['module_id', 'order']);
            $table->index(['module_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
