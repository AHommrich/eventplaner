<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->foreignId('album_id')->nullable()->after('event_id')->constrained('photo_albums')->nullOnDelete();
            $table->string('r2_key', 500)->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['album_id']);
            $table->dropColumn(['album_id', 'r2_key']);
        });
    }
};
