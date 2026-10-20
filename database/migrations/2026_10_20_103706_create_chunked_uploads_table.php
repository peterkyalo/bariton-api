<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunked_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('chunk_size_bytes');
            $table->unsignedSmallInteger('total_chunks');
            $table->json('chunks_completed')->nullable();
            $table->string('status')->default('uploading'); // uploading, completed, expired, failed
            $table->string('storage_disk')->default('local');
            $table->string('temp_directory');
            $table->string('final_path')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunked_uploads');
    }
};
