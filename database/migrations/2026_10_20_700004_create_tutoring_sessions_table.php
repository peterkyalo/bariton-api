<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutoring_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_tutoring_package_id')->constrained('student_tutoring_packages')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->dateTime('scheduled_start');
            $table->dateTime('scheduled_end');
            $table->string('mode')->default('zoom'); // zoom, google_meet, in_person
            $table->string('meeting_link')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, rescheduled, attended, missed, cancelled_by_student, cancelled_by_instructor
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('rescheduled_from_session_id')->nullable()->constrained('tutoring_sessions')->nullOnDelete();
            $table->boolean('notice_waived')->default(false);
            $table->string('waiver_reason')->nullable();
            $table->text('instructor_notes')->nullable();
            $table->timestamps();

            $table->index(['instructor_id', 'scheduled_start', 'status'], 'tutor_sess_instr_start_idx');
            $table->index(['student_id', 'scheduled_start', 'status'], 'tutor_sess_student_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutoring_sessions');
    }
};
