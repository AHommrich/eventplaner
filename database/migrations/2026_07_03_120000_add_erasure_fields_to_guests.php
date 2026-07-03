<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields powering the guest-side GDPR Art. 17 (erasure) flow.
 *
 *  - erasure_requested_at    : when the guest hit "delete my data" in the app.
 *  - scheduled_erasure_at    : now + `retention.guest_erasure_grace_days`. The
 *                              purge command deletes rows past that timestamp.
 *  - erasure_recovery_token  : sha256 hash of a one-time token that lets the
 *                              guest revoke the request within the grace
 *                              window without going through QR-login again
 *                              (the erasure request revokes their Sanctum
 *                              tokens on purpose).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->timestamp('erasure_requested_at')->nullable()->after('drinks_access');
            $table->timestamp('scheduled_erasure_at')->nullable()->after('erasure_requested_at');
            $table->string('erasure_recovery_token', 64)->nullable()->after('scheduled_erasure_at');

            $table->index('scheduled_erasure_at');
            $table->index('erasure_recovery_token');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['erasure_recovery_token']);
            $table->dropIndex(['scheduled_erasure_at']);
            $table->dropColumn(['erasure_recovery_token', 'scheduled_erasure_at', 'erasure_requested_at']);
        });
    }
};
