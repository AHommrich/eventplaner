<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Erst bestehende revocation_pending-Werte umschreiben
        DB::statement("UPDATE guests SET rsvp_status = 'declined_pending' WHERE rsvp_status = 'revocation_pending'");

        // Enum neu definieren (MariaDB: MODIFY statt change())
        DB::statement("ALTER TABLE guests MODIFY COLUMN rsvp_status ENUM('accepted_pending','accepted','declined_pending','declined') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE guests MODIFY COLUMN rsvp_status ENUM('accepted','declined','revocation_pending') NULL DEFAULT NULL");
    }
};
