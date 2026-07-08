<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-photo hide: a guest hides a single photo from their own view. Created
 * automatically when the guest reports a photo (App Store Guideline 1.2
 * requires that objectionable content disappears for the reporter without
 * waiting for moderator resolution).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewer_guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['viewer_guest_id', 'photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_hides');
    }
};
