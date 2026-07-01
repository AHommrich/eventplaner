<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drink_catalog', function (Blueprint $table) {
            $table->string('display_name_en')->nullable()->after('display_name');
        });

        $translations = [
            'pils' => 'Pilsner',
            'weizen' => 'Wheat Beer',
            'helles' => 'Helles Lager',
            'kellerbier' => 'Kellerbier',
            'altbier' => 'Altbier',
            'koelsch' => 'Kölsch',
            'radler' => 'Radler (Shandy)',
            'bockbier' => 'Bock Beer',
            'dunkles' => 'Dark Beer',
            'craft_beer' => 'Craft Beer',
            'beer_free' => 'Non-Alcoholic Beer',
            'white_wine' => 'White Wine',
            'red_wine' => 'Red Wine',
            'rose' => 'Rosé',
            'sekt' => 'Sparkling Wine',
            'prosecco' => 'Prosecco',
            'weinschorle' => 'Wine Spritzer',
            'gluehwein' => 'Mulled Wine',
            'champagne' => 'Champagne',
            'vodka' => 'Vodka',
            'jaeger' => 'Jägermeister',
            'korn' => 'Korn Schnapps',
            'tequila' => 'Tequila',
            'rum' => 'Rum',
            'gin' => 'Gin',
            'whiskey' => 'Whiskey',
            'bourbon' => 'Bourbon',
            'sambuca' => 'Sambuca',
            'liqueur' => 'Liqueur',
            'amaretto' => 'Amaretto',
            'baileys' => 'Baileys',
            'malibu' => 'Malibu',
            'gin_tonic' => 'Gin & Tonic',
            'vodka_lemon' => 'Vodka Lemon',
            'rum_cola' => 'Rum & Cola',
            'whiskey_cola' => 'Whiskey & Cola',
            'aperol_spritz' => 'Aperol Spritz',
            'hugo' => 'Hugo',
            'vodka_energy' => 'Vodka Energy',
            'asbach_cola' => 'Asbach & Cola',
            'mariacron_cola' => 'Mariacron & Cola',
            'korn_fanta' => 'Korn & Fanta',
            'fanta_ficken' => 'Fanta F***en',
            'malibu_maracuja' => 'Malibu Passion Fruit',
            'vodka_orange' => 'Vodka Orange',
            'rum_orange' => 'Rum & Orange',
            'gin_soda' => 'Gin & Soda',
            'malibu_orange' => 'Malibu Orange',
            'mojito' => 'Mojito',
            'caipirinha' => 'Caipirinha',
            'pina_colada' => 'Piña Colada',
            'cuba_libre' => 'Cuba Libre',
            'margarita' => 'Margarita',
            'long_island_iced_tea' => 'Long Island Iced Tea',
            'still_water' => 'Still Water',
            'medium_water' => 'Medium Sparkling Water',
            'sparkling_water' => 'Sparkling Water',
            'cola' => 'Cola',
            'cola_zero' => 'Cola Zero',
            'fanta' => 'Fanta',
            'sprite' => 'Sprite',
            'spezi' => 'Cola & Orange Mix',
            'tonic_water' => 'Tonic Water',
            'ginger_ale' => 'Ginger Ale',
            'ice_tea' => 'Iced Tea',
            'energy_drink' => 'Energy Drink',
            'club_mate' => 'Club Mate',
            'coffee' => 'Coffee',
            'cappuccino' => 'Cappuccino',
            'latte' => 'Latte Macchiato',
            'espresso' => 'Espresso',
            'tea' => 'Tea',
        ];

        foreach ($translations as $type => $nameEn) {
            DB::table('drink_catalog')
                ->where('type', $type)
                ->update(['display_name_en' => $nameEn]);
        }
    }

    public function down(): void
    {
        Schema::table('drink_catalog', function (Blueprint $table) {
            $table->dropColumn('display_name_en');
        });
    }
};
