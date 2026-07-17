<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_pairings', function (Blueprint $table) {
            $table->foreignId('event_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        // Existing management PATs were user-wide and cannot be assigned to an
        // event without silently broadening or guessing their authority. User
        // PATs are management-only in this application, so revoke them all;
        // their pairing and push rows follow through the existing cascades.
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->delete();
        DB::table('device_pairings')->delete();

        // MariaDB keeps integer attributes predictable with an explicit ALTER;
        // using change() has previously dropped UNSIGNED in this project.
        DB::statement('ALTER TABLE device_pairings MODIFY event_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE device_pairings MODIFY event_id BIGINT UNSIGNED NULL');

        Schema::table('device_pairings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
