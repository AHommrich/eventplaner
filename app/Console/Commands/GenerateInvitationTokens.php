<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Guest;
use App\Models\InvitationToken;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateInvitationTokens extends Command
{
    protected $signature = 'invitations:generate
                            {--force : Bestehende Tokens überschreiben}';

    protected $description = 'Generiert Einladungstoken für alle Gruppen und Solo-Gäste';

    public function handle(): void
    {
        $force = $this->option('force');
        $created = 0;
        $skipped = 0;

        // 1. Einen Token pro Gruppe
        Group::all()->each(function (Group $group) use ($force, &$created, &$skipped) {
            $exists = InvitationToken::where('group_id', $group->id)->exists();

            if ($exists && !$force) {
                $skipped++;
                return;
            }

            InvitationToken::updateOrCreate(
                ['group_id' => $group->id],
                ['token' => Str::random(32), 'guest_id' => null],
            );
            $created++;
        });

        // 2. Einen Token pro Gast ohne Gruppe
        Guest::whereNull('group_id')->each(function (Guest $guest) use ($force, &$created, &$skipped) {
            $exists = InvitationToken::where('guest_id', $guest->id)->exists();

            if ($exists && !$force) {
                $skipped++;
                return;
            }

            InvitationToken::updateOrCreate(
                ['guest_id' => $guest->id],
                ['token' => Str::random(32), 'group_id' => null],
            );
            $created++;
        });

        $this->info("Fertig: {$created} Token(s) erstellt/aktualisiert, {$skipped} übersprungen.");
        $this->line('Nutze --force um bestehende Tokens zu überschreiben.');
    }
}
