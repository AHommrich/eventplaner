<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_token_id')->nullable()->constrained()->nullOnDelete();
            $table->string('expo_ticket_id')->unique();
            $table->string('receipt_status', 32)->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['checked_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tickets');
    }
};
