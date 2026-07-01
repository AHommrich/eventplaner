<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a timestamp recording when the user accepted the privacy policy at signup.
 * Existing users (created before this column existed) are back-filled with their
 * `created_at` so we don't have a wave of NULLs masquerading as "never accepted".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacy_accepted_at')->nullable()->after('email_verified_at');
        });

        // Back-fill: anyone who was already registered before this column landed
        // implicitly accepted the policy at signup — copy from created_at so
        // existing rows aren't indistinguishable from "explicitly rejected".
        \DB::table('users')
            ->whereNull('privacy_accepted_at')
            ->update(['privacy_accepted_at' => \DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('privacy_accepted_at');
        });
    }
};
