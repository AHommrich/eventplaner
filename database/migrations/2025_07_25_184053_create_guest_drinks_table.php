<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('guest_drinks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('guest_id')->constrained('guests')->onDelete('cascade');
        $table->string('drink_type'); // z.B. beer, wine
        $table->unsignedTinyInteger('thirst_level')->default(1); // 1-10
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_drinks');
    }
};
