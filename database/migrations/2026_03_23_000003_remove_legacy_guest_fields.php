<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'likelihood', 'invite']);
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('likelihood')->nullable();
            $table->boolean('invite')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }
};
