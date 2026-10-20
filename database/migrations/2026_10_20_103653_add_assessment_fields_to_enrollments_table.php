<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedTinyInteger('assignment_pass_mark_percent')->default(70)->after('status');
            $table->unsignedTinyInteger('exam_pass_mark_percent')->default(70)->after('assignment_pass_mark_percent');
            $table->unsignedTinyInteger('exam_attempt_limit')->default(3)->after('exam_pass_mark_percent');
            $table->unsignedSmallInteger('exam_wait_time_hours')->default(24)->after('exam_attempt_limit');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'assignment_pass_mark_percent',
                'exam_pass_mark_percent',
                'exam_attempt_limit',
                'exam_wait_time_hours',
            ]);
        });
    }
};
