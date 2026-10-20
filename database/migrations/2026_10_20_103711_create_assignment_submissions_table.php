<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('file_path');
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('media_type')->default('video'); // audio, video
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->text('student_notes')->nullable();
            $table->string('status')->default('submitted'); // submitted, grading, graded, resubmission_required
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('total_points_earned')->nullable();
            $table->unsignedSmallInteger('total_points_possible')->nullable();
            $table->boolean('passed')->nullable();
            $table->longText('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->index(['assignment_id', 'user_id', 'attempt_number'], 'asgn_sub_attempt_idx');
            $table->index(['enrollment_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('submission_rubric_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_submission_id')->constrained('assignment_submissions')->cascadeOnDelete();
            $table->foreignId('rubric_criterion_id')->constrained('rubric_criteria')->cascadeOnDelete();
            $table->foreignId('rubric_level_id')->constrained('rubric_levels')->cascadeOnDelete();
            $table->unsignedSmallInteger('points_awarded')->default(0);
            $table->text('criterion_feedback')->nullable();
            $table->timestamps();

            $table->index(['assignment_submission_id', 'rubric_criterion_id'], 'sub_rubric_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_rubric_scores');
        Schema::dropIfExists('assignment_submissions');
    }
};
