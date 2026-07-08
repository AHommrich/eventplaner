<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo report submitted by a guest against a specific photo they can see in
 * the app gallery. Backs the App-Store-Guideline-1.2 report mechanism and the
 * GDPR Art. 21 objection path (guests who did not consent to being on an owner
 * upload can also raise it here — reported_guest_id stays null in that case).
 *
 * event_id is denormalised so the /requests hub can scope reports by active
 * event without joining photos on every fetch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->foreignId('reporter_guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('reported_guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->enum('reason', ['inappropriate_content', 'privacy', 'other']);
            $table->text('message')->nullable();
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index('photo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_reports');
    }
};
