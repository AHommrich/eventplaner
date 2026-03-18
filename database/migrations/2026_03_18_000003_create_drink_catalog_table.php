<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('drink_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('category');           // beer, wine, spirit, longdrink, cocktail, softdrink, water, coffee
            $table->string('type');               // pils, weizen, vodka, ...
            $table->string('display_name');       // "Pils 0,5 l"
            $table->float('amount_liter');        // 0.5
            $table->float('alcohol_percent');     // 4.9 | 0.0 bei alkoholfrei
            $table->boolean('is_alcoholic');
            $table->integer('negative_points')->nullable(); // nur bei alkoholfrei: -5 (Wasser) oder -3
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->nullable();
            $table->json('search_terms')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('type');
            $table->index('is_alcoholic');
            $table->index('is_active');
            $table->unique(['type', 'amount_liter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drink_catalog');
    }
};
