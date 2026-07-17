<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes & ToDos — a lightweight per-event task list for organizers.
 *
 * `assignee_user_id = null` → personal (author's own). A non-null assignee is an
 * owner/event_admin-assigned task for an active event_manager.
 *
 * FK nullability is mandatory: user deletion in this project is a HARD delete
 * (no SoftDeletes on User), so author/assignee FKs must be nullable +
 * nullOnDelete — otherwise deleting a user would fail the constraint. The
 * `author_name` snapshot keeps "created by …" readable after the account is gone.
 * Never cascade-delete an event's task list because one person left.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 16)->default('note'); // 'note' | 'todo'
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('assignee_user_id');
            $table->index('is_done');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
