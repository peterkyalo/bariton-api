<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic learning materials attachable to courses and modules
        // (YouTube video, PDF, audio or image).
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('attachable');
            $table->string('kind'); // youtube, pdf, audio, image
            $table->string('title')->nullable();
            $table->string('youtube_video_id')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_disk')->default('local');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
            $table->string('image_disk')->default('public')->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_disk']);
        });

        Schema::dropIfExists('attachments');
    }
};
