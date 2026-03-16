<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invitation_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('family_id');
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('invitation_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
            $table->foreignId('family_id')->nullable()->constrained('groups')->nullOnDelete();
        });
    }
};
