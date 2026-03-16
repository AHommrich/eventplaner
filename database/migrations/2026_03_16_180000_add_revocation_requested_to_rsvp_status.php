<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE guests MODIFY COLUMN rsvp_status ENUM('accepted_pending','accepted','declined_pending','declined','revocation_requested') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE guests SET rsvp_status = 'declined' WHERE rsvp_status = 'revocation_requested'");
        DB::statement("ALTER TABLE guests MODIFY COLUMN rsvp_status ENUM('accepted_pending','accepted','declined_pending','declined') NULL DEFAULT NULL");
    }
};
