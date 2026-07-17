<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the vestigial `categories` table.
 *
 * Categories were unlinked from guests long ago (the `guests.category_id`
 * column was dropped in an earlier refactor). What remained — the table, the
 * `Category` model, `CategoryController::store` and an unread `categories`
 * prop — was dead code with no read path and an unscoped write route. Rather
 * than scope a feature nobody uses per event (P0.1), we remove it entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('categories');
    }

    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }
};
