<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration B: event_task_overrides Tabelle anlegen
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_task_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('photo_game_tasks')->cascadeOnDelete();
            $table->enum('action', ['hidden', 'modified', 'added']);
            $table->text('custom_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_task_overrides');
    }
};
