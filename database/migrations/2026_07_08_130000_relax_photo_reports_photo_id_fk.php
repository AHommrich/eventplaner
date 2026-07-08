<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relax photo_reports.photo_id so the audit row survives a photo delete.
 *
 * The original schema used cascadeOnDelete, which meant deleting a reported
 * photo also removed the corresponding photo_reports row — losing the
 * moderation trail. We instead nullify photo_id on photo deletion so the
 * report keeps status/resolver/timestamps as a permanent record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_reports', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
        });

        Schema::table('photo_reports', function (Blueprint $table) {
            $table->foreignId('photo_id')->nullable()->change();
        });

        Schema::table('photo_reports', function (Blueprint $table) {
            $table->foreign('photo_id')->references('id')->on('photos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('photo_reports', function (Blueprint $table) {
            $table->dropForeign(['photo_id']);
        });

        Schema::table('photo_reports', function (Blueprint $table) {
            $table->foreignId('photo_id')->nullable(false)->change();
        });

        Schema::table('photo_reports', function (Blueprint $table) {
            $table->foreign('photo_id')->references('id')->on('photos')->cascadeOnDelete();
        });
    }
};
