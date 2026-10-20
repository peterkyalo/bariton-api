<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('status', 32)->default('active')->index()->after('date_of_birth');
            $table->timestamp('privacy_consent_at')->nullable()->after('status');
            $table->string('privacy_policy_version', 16)->nullable()->after('privacy_consent_at');
            $table->timestamp('locked_until')->nullable()->after('privacy_policy_version');
            $table->unsignedSmallInteger('failed_login_count')->default(0)->after('locked_until');
            $table->timestamp('deletion_requested_at')->nullable()->after('failed_login_count');
            $table->text('deletion_reason')->nullable()->after('deletion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'date_of_birth',
                'status',
                'privacy_consent_at',
                'privacy_policy_version',
                'locked_until',
                'failed_login_count',
                'deletion_requested_at',
                'deletion_reason',
            ]);
        });
    }
};
