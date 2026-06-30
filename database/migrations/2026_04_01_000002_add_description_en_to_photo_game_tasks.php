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
            $table->string('description_en')->nullable()->after('description');
        });

        $translations = [
            'photoGame.tasks.general.laughing' => 'Someone laughing',
            'photoGame.tasks.general.dancing' => 'Someone dancing',
            'photoGame.tasks.general.talking' => 'Two people in conversation',
            'photoGame.tasks.general.funnyGroup' => 'A funny group photo',
            'photoGame.tasks.general.hug' => 'A hug',
            'photoGame.tasks.general.weirdPose' => 'Someone in an unusual pose',
            'photoGame.tasks.general.twoGenerations' => 'Two generations together',
            'photoGame.tasks.general.drink' => 'A drink',
            'photoGame.tasks.general.mustHave' => 'Something no party should be without',
            'photoGame.tasks.general.eating' => 'Someone eating',
            'photoGame.tasks.general.buffet' => 'The buffet / snacks',
            'photoGame.tasks.general.decoration' => 'The decoration overview',
            'photoGame.tasks.general.reunited' => 'Two people who haven\'t seen each other in a long time',
            'photoGame.tasks.general.playing' => 'Someone playing a game',
            'photoGame.tasks.general.candid' => 'Someone who doesn\'t know they\'re being photographed',

            'photoGame.tasks.hochzeit.firstDance' => 'The couple\'s first dance',
            'photoGame.tasks.hochzeit.tableNeighbor' => 'Selfie with your table neighbor',
            'photoGame.tasks.hochzeit.cake' => 'The wedding cake',
            'photoGame.tasks.hochzeit.rings' => 'The wedding rings',
            'photoGame.tasks.hochzeit.entireParty' => 'The entire wedding party (if possible)',
            'photoGame.tasks.hochzeit.somethingBlue' => 'Something blue at the wedding',
            'photoGame.tasks.hochzeit.eldersDancing' => 'Two older guests dancing',
            'photoGame.tasks.hochzeit.kiss' => 'A kiss',
            'photoGame.tasks.hochzeit.flowerDeco' => 'The flower decorations',

            'photoGame.tasks.geburtstag.birthdayCake' => 'The birthday cake',
            'photoGame.tasks.geburtstag.withChild' => 'A photo with the birthday person',
            'photoGame.tasks.geburtstag.singing' => 'Someone singing',
            'photoGame.tasks.geburtstag.bestWrapping' => 'The best gift wrapping',
            'photoGame.tasks.geburtstag.colorfulOutfit' => 'The most colorful outfit',
            'photoGame.tasks.geburtstag.oldestYoungest' => 'The oldest and youngest guest together',
            'photoGame.tasks.geburtstag.groupPhoto' => 'A group photo with at least 5 people',
            'photoGame.tasks.geburtstag.presents' => 'The gifts',
        ];

        foreach ($translations as $key => $descriptionEn) {
            DB::table('photo_game_tasks')
                ->where('translation_key', $key)
                ->update(['description_en' => $descriptionEn]);
        }
    }

    public function down(): void
    {
        Schema::table('photo_game_tasks', function (Blueprint $table) {
            $table->dropColumn('description_en');
        });
    }
};
