<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->enum('rsvp_status', ['accepted', 'declined', 'revocation_pending'])
                ->nullable()
                ->default(null)
                ->after('likelihood');

            // Welcher Gast hat den Status gesetzt (Familienmitglied oder Gast selbst)
            $table->foreignId('rsvp_set_by_guest_id')
                ->nullable()
                ->after('rsvp_status')
                ->constrained('guests')
                ->nullOnDelete();

            // Welcher Web-User hat den Status gesetzt (Event-Owner oder Admin)
            $table->foreignId('rsvp_set_by_user_id')
                ->nullable()
                ->after('rsvp_set_by_guest_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('rsvp_set_at')->nullable()->after('rsvp_set_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropForeign(['rsvp_set_by_guest_id']);
            $table->dropForeign(['rsvp_set_by_user_id']);
            $table->dropColumn(['rsvp_status', 'rsvp_set_by_guest_id', 'rsvp_set_by_user_id', 'rsvp_set_at']);
        });
    }
};
