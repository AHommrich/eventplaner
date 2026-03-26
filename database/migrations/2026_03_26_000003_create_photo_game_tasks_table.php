<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_game_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_id')->constrained('photo_game_task_catalogs')->cascadeOnDelete();
            $table->text('description');
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_game_tasks');
    }
};
