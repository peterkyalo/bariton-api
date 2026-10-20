<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('status')->default('in_progress'); // in_progress, submitted, grading, graded
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('recording_stopped_at')->nullable();
            $table->timestamp('upload_grace_period_expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->decimal('max_score', 6, 2)->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->text('overall_feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'user_id', 'attempt_number']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('exam_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('exam_question_id')->constrained('exam_questions')->cascadeOnDelete();
            $table->string('selected_option')->nullable(); // For multiple choice
            $table->longText('text_answer')->nullable(); // For theory answers auto-saved with debounce
            $table->string('media_path')->nullable(); // For recorded performance uploads
            $table->string('media_disk')->nullable();
            $table->string('media_mime')->nullable();
            $table->boolean('is_auto_graded')->default(false);
            $table->decimal('score_awarded', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'exam_question_id'], 'attempt_question_unique');
        });

        Schema::create('exam_rubric_scores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('exam_attempt_answer_id')->constrained('exam_attempt_answers')->cascadeOnDelete();
            $table->foreignId('rubric_criterion_id')->constrained('rubric_criteria')->cascadeOnDelete();
            $table->foreignId('rubric_level_id')->nullable()->constrained('rubric_levels')->nullOnDelete();
            $table->decimal('points_awarded', 6, 2)->default(0);
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['exam_attempt_answer_id', 'rubric_criterion_id'], 'rubric_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_rubric_scores');
        Schema::dropIfExists('exam_attempt_answers');
        Schema::dropIfExists('exam_attempts');
    }
};
