<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 50);
            $table->string('name', 255);
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_albums');
    }
};
