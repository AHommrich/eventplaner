<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $events = DB::table('events')->whereNull('projector_token')->get(['id']);

        foreach ($events as $event) {
            DB::table('events')
                ->where('id', $event->id)
                ->update(['projector_token' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        // not reversible
    }
};
