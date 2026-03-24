<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('projector_token', 64)->nullable()->unique()->after('drink_game_end_time');
            $table->foreignId('projector_album_id')->nullable()->after('projector_token')->constrained('photo_albums')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['projector_album_id']);
            $table->dropColumn(['projector_token', 'projector_album_id']);
        });
    }
};
