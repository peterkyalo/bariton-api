<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('certificate_code')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('exam_attempt_id')->nullable()->constrained('exam_attempts')->nullOnDelete();
            $table->string('holder_name');
            $table->string('course_title');
            $table->string('path_type')->default('standard'); // standard, validation
            $table->string('status')->default('issued'); // issued, revoked, replaced
            $table->string('reissue_replaced_by_code')->nullable();
            $table->string('reissue_replaces_code')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_disk')->nullable();
            $table->longText('qr_code_svg')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['user_id', 'course_id', 'status']);
            $table->index('certificate_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
