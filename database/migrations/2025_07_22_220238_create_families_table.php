<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // z.B. "Caspari"
            $table->timestamps();
        });

        // guests table ergänzen
        Schema::table('guests', function (Blueprint $table) {
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('family_id');
        });

        Schema::dropIfExists('families');
    }
};
