<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_game_tasks', function (Blueprint $table) {
            $table->string('translation_key')->nullable()->after('description');
        });

        // Stabile Keys für alle globalen Tasks setzen (event_id = null)
        $keys = [
            // Allgemein (is_base = true)
            'Jemanden beim Lachen'                                          => 'photoGame.tasks.general.laughing',
            'Jemanden beim Tanzen'                                          => 'photoGame.tasks.general.dancing',
            'Zwei Menschen im Gespräch'                                     => 'photoGame.tasks.general.talking',
            'Ein lustiges Gruppenbild'                                      => 'photoGame.tasks.general.funnyGroup',
            'Eine Umarmung'                                                 => 'photoGame.tasks.general.hug',
            'Jemanden in ungewöhnlicher Pose'                               => 'photoGame.tasks.general.weirdPose',
            'Zwei Generationen zusammen'                                    => 'photoGame.tasks.general.twoGenerations',
            'Ein Getränk'                                                   => 'photoGame.tasks.general.drink',
            'Etwas das auf keiner Party fehlen darf'                        => 'photoGame.tasks.general.mustHave',
            'Jemanden beim Essen'                                           => 'photoGame.tasks.general.eating',
            'Das Buffet / die Snacks'                                       => 'photoGame.tasks.general.buffet',
            'Die Deko im Überblick'                                         => 'photoGame.tasks.general.decoration',
            'Zwei Menschen die sich lange nicht / noch nie gesehen haben'   => 'photoGame.tasks.general.reunited',
            'Jemanden beim Spielen'                                         => 'photoGame.tasks.general.playing',
            'Jemanden der gerade nicht weiß dass er fotografiert wird'      => 'photoGame.tasks.general.candid',

            // Hochzeit (event_type = 'hochzeit')
            'Das Brautpaar beim ersten Tanz'                                => 'photoGame.tasks.hochzeit.firstDance',
            'Selfie mit dem Tischnachbarn'                                  => 'photoGame.tasks.hochzeit.tableNeighbor',
            'Die Torte'                                                     => 'photoGame.tasks.hochzeit.cake',
            'Die Ringe'                                                     => 'photoGame.tasks.hochzeit.rings',
            'Die ganze Hochzeitsgesellschaft (wenn möglich)'                => 'photoGame.tasks.hochzeit.entireParty',
            'Etwas Blaues auf der Hochzeit'                                 => 'photoGame.tasks.hochzeit.somethingBlue',
            'Zwei ältere Gäste beim Tanzen'                                 => 'photoGame.tasks.hochzeit.eldersDancing',
            'Ein Kuss'                                                      => 'photoGame.tasks.hochzeit.kiss',
            'Die Blumendeko'                                                => 'photoGame.tasks.hochzeit.flowerDeco',

            // Geburtstag (event_type = 'geburtstag')
            'Den Geburtstagskuchen'                                         => 'photoGame.tasks.geburtstag.birthdayCake',
            'Ein Foto mit dem Geburtstagskind'                              => 'photoGame.tasks.geburtstag.withChild',
            'Jemanden beim Singen'                                          => 'photoGame.tasks.geburtstag.singing',
            'Die schönste Geschenkverpackung'                               => 'photoGame.tasks.geburtstag.bestWrapping',
            'Das bunteste Outfit'                                           => 'photoGame.tasks.geburtstag.colorfulOutfit',
            'Den ältesten und den jüngsten Gast zusammen'                   => 'photoGame.tasks.geburtstag.oldestYoungest',
            'Ein Gruppenphoto mit mindestens 5 Personen'                    => 'photoGame.tasks.geburtstag.groupPhoto',
            'Die Geschenke'                                                 => 'photoGame.tasks.geburtstag.presents',
        ];

        // UPDATE...JOIN ist MySQL-spezifisch; rewrite mit Subquery, läuft auf MySQL und SQLite.
        foreach ($keys as $description => $key) {
            DB::table('photo_game_tasks')
                ->where('description', $description)
                ->whereIn('catalog_id', function ($q) {
                    $q->select('id')->from('photo_game_task_catalogs')->whereNull('event_id');
                })
                ->update(['translation_key' => $key]);
        }
    }

    public function down(): void
    {
        Schema::table('photo_game_tasks', function (Blueprint $table) {
            $table->dropColumn('translation_key');
        });
    }
};
