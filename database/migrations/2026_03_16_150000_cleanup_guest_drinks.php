<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // beer/wine-Spalten aus guests entfernen
        Schema::table('guests', function (Blueprint $table) {
            if (Schema::hasColumn('guests', 'beer')) $table->dropColumn('beer');
            if (Schema::hasColumn('guests', 'wine')) $table->dropColumn('wine');
        });

        // guest_drinks-Tabelle entfernen
        Schema::dropIfExists('guest_drinks');
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->boolean('beer')->default(false);
            $table->boolean('wine')->default(false);
        });

        Schema::create('guest_drinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->string('drink_type');
            $table->unsignedTinyInteger('thirst_level')->default(1);
            $table->timestamps();
        });
    }
};
