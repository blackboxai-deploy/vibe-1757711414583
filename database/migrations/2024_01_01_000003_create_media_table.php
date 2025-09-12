<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_name');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type');
            $table->bigInteger('size'); // File size in bytes
            $table->json('dimensions')->nullable(); // width, height for images/videos
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('media_category_id')->constrained()->onDelete('cascade');
            $table->json('metadata')->nullable(); // EXIF data, duration, etc.
            $table->text('description')->nullable();
            $table->json('tags')->nullable(); // Array of tags
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};