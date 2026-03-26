<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_game_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('event_photo_games')->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('photo_game_tasks');
            $table->foreignId('photo_id')->nullable()->constrained('photos')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'guest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_game_assignments');
    }
};
