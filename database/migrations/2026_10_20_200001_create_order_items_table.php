<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('item_type'); // module, course_bundle, bundle_upgrade, assessment_package, exam_retake_package, bootcamp_booking, tutoring_package
            $table->unsignedBigInteger('item_id'); // target entity id
            $table->string('title');
            $table->unsignedBigInteger('price_kes'); // minor units
            $table->unsignedBigInteger('vat_kes')->default(0); // minor units
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
