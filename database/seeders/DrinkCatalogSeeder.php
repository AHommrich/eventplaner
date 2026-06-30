<?php

namespace Database\Seeders;

use App\Models\DrinkCatalog;
use App\Models\DrinkCatalogSize;
use Illuminate\Database\Seeder;

class DrinkCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [

            // ─── BIER ────────────────────────────────────────────────────────────
            ['category' => 'beer', 'type' => 'pils',       'base_name' => 'Pils',               'alcohol_percent' => 4.9, 'is_alcoholic' => true,  'sizes' => [0.25, 0.33, 0.5],  'search_terms' => ['Bier', 'Pils', 'Lager'],                          'sort_order_base' => 10],
            ['category' => 'beer', 'type' => 'weizen',     'base_name' => 'Weizen',             'alcohol_percent' => 5.1, 'is_alcoholic' => true,  'sizes' => [0.25, 0.33, 0.5],  'search_terms' => ['Bier', 'Weizen', 'Weißbier', 'Hefeweizen'],       'sort_order_base' => 20],
            ['category' => 'beer', 'type' => 'helles',     'base_name' => 'Helles',             'alcohol_percent' => 4.8, 'is_alcoholic' => true,  'sizes' => [0.25, 0.33, 0.5],  'search_terms' => ['Bier', 'Helles', 'Lagerbier', 'Bayern'],           'sort_order_base' => 30],
            ['category' => 'beer', 'type' => 'kellerbier', 'base_name' => 'Kellerbier',         'alcohol_percent' => 5.0, 'is_alcoholic' => true,  'sizes' => [0.33, 0.5],         'search_terms' => ['Bier', 'Kellerbier', 'Zwickel', 'ungefiltert'],   'sort_order_base' => 40],
            ['category' => 'beer', 'type' => 'altbier',    'base_name' => 'Altbier',            'alcohol_percent' => 4.8, 'is_alcoholic' => true,  'sizes' => [0.25, 0.33, 0.5],  'search_terms' => ['Bier', 'Altbier', 'Alt', 'Düsseldorf'],           'sort_order_base' => 50],
            ['category' => 'beer', 'type' => 'koelsch',    'base_name' => 'Kölsch',             'alcohol_percent' => 4.8, 'is_alcoholic' => true,  'sizes' => [0.2, 0.33],         'search_terms' => ['Bier', 'Kölsch', 'Köln'],                         'sort_order_base' => 60],
            ['category' => 'beer', 'type' => 'radler',     'base_name' => 'Radler',             'alcohol_percent' => 2.5, 'is_alcoholic' => true,  'sizes' => [0.33, 0.5],         'search_terms' => ['Bier', 'Radler', 'Shandy', 'Limo'],               'sort_order_base' => 70],
            ['category' => 'beer', 'type' => 'bockbier',   'base_name' => 'Bockbier',           'alcohol_percent' => 6.5, 'is_alcoholic' => true,  'sizes' => [0.33, 0.5],         'search_terms' => ['Bier', 'Bock', 'Bockbier', 'Starkbier'],          'sort_order_base' => 80],
            ['category' => 'beer', 'type' => 'dunkles',    'base_name' => 'Dunkles',            'alcohol_percent' => 5.2, 'is_alcoholic' => true,  'sizes' => [0.25, 0.33, 0.5],  'search_terms' => ['Bier', 'Dunkles', 'Dunkelbier'],                  'sort_order_base' => 90],
            ['category' => 'beer', 'type' => 'craft_beer', 'base_name' => 'Craft Beer',         'alcohol_percent' => 5.5, 'is_alcoholic' => true,  'sizes' => [0.33, 0.5],         'search_terms' => ['Bier', 'Craft Beer', 'Craftbeer', 'IPA'],          'sort_order_base' => 100],
            ['category' => 'beer', 'type' => 'beer_free',  'base_name' => 'Alkoholfreies Bier', 'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.33, 0.5], 'search_terms' => ['Bier', 'alkoholfrei', 'AF'],  'sort_order_base' => 110],

            // ─── WEIN ────────────────────────────────────────────────────────────
            ['category' => 'wine', 'type' => 'white_wine',  'base_name' => 'Weißwein',   'alcohol_percent' => 12.0, 'is_alcoholic' => true, 'sizes' => [0.1, 0.2],  'search_terms' => ['Wein', 'Weißwein', 'weiß'],                          'sort_order_base' => 10],
            ['category' => 'wine', 'type' => 'red_wine',    'base_name' => 'Rotwein',    'alcohol_percent' => 13.0, 'is_alcoholic' => true, 'sizes' => [0.1, 0.2],  'search_terms' => ['Wein', 'Rotwein', 'rot'],                            'sort_order_base' => 20],
            ['category' => 'wine', 'type' => 'rose',        'base_name' => 'Rosé',       'alcohol_percent' => 11.5, 'is_alcoholic' => true, 'sizes' => [0.1, 0.2],  'search_terms' => ['Wein', 'Rosé', 'Rosewein', 'rosa'],                  'sort_order_base' => 30],
            ['category' => 'wine', 'type' => 'sekt',        'base_name' => 'Sekt',       'alcohol_percent' => 11.0, 'is_alcoholic' => true, 'sizes' => [0.1, 0.2],  'search_terms' => ['Sekt', 'Schaumwein', 'Perlwein'],                    'sort_order_base' => 40],
            ['category' => 'wine', 'type' => 'prosecco',    'base_name' => 'Prosecco',   'alcohol_percent' => 11.0, 'is_alcoholic' => true, 'sizes' => [0.1, 0.2],  'search_terms' => ['Prosecco', 'Schaumwein', 'Wein', 'Italien'],         'sort_order_base' => 50],
            ['category' => 'wine', 'type' => 'weinschorle', 'base_name' => 'Weinschorle', 'alcohol_percent' => 6.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3],  'search_terms' => ['Wein', 'Schorle', 'Weinschorle'],                   'sort_order_base' => 60],
            ['category' => 'wine', 'type' => 'gluehwein',   'base_name' => 'Glühwein',   'alcohol_percent' => 9.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3],  'search_terms' => ['Glühwein', 'Wein', 'warm', 'Weihnachten'],          'sort_order_base' => 70],
            ['category' => 'wine', 'type' => 'champagne',   'base_name' => 'Champagner', 'alcohol_percent' => 12.0, 'is_alcoholic' => true, 'sizes' => [0.1, 0.2],  'search_terms' => ['Champagner', 'Champagne', 'Schaumwein', 'Frankreich'], 'sort_order_base' => 80],

            // ─── SPIRITUOSEN (SHOTS) ──────────────────────────────────────────────
            ['category' => 'spirit', 'type' => 'vodka',    'base_name' => 'Wodka',       'alcohol_percent' => 40.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Wodka', 'Vodka', 'Shot', 'Schnaps'],         'sort_order_base' => 10],
            ['category' => 'spirit', 'type' => 'jaeger',   'base_name' => 'Jägermeister', 'alcohol_percent' => 35.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Jägermeister', 'Jäger', 'Shot', 'Kräuter'],  'sort_order_base' => 20],
            ['category' => 'spirit', 'type' => 'korn',     'base_name' => 'Korn',        'alcohol_percent' => 32.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Korn', 'Schnaps', 'Shot'],                    'sort_order_base' => 30],
            ['category' => 'spirit', 'type' => 'tequila',  'base_name' => 'Tequila',     'alcohol_percent' => 38.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Tequila', 'Shot', 'Agave'],                  'sort_order_base' => 40],
            ['category' => 'spirit', 'type' => 'rum',      'base_name' => 'Rum',         'alcohol_percent' => 40.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Rum', 'Shot'],                               'sort_order_base' => 50],
            ['category' => 'spirit', 'type' => 'gin',      'base_name' => 'Gin',         'alcohol_percent' => 40.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Gin', 'Shot', 'Wacholder'],                  'sort_order_base' => 60],
            ['category' => 'spirit', 'type' => 'whiskey',  'base_name' => 'Whiskey',     'alcohol_percent' => 40.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Whiskey', 'Whisky', 'Scotch', 'Shot'],       'sort_order_base' => 70],
            ['category' => 'spirit', 'type' => 'bourbon',  'base_name' => 'Bourbon',     'alcohol_percent' => 40.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Bourbon', 'Whiskey', 'Shot', 'Amerika'],     'sort_order_base' => 80],
            ['category' => 'spirit', 'type' => 'sambuca',  'base_name' => 'Sambuca',     'alcohol_percent' => 38.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Sambuca', 'Anis', 'Shot'],                   'sort_order_base' => 90],
            ['category' => 'spirit', 'type' => 'liqueur',  'base_name' => 'Likör',       'alcohol_percent' => 25.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Likör', 'Likoer', 'Shot', 'süß'],            'sort_order_base' => 100],
            ['category' => 'spirit', 'type' => 'amaretto', 'base_name' => 'Amaretto',    'alcohol_percent' => 28.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Amaretto', 'Mandel', 'Shot'],                'sort_order_base' => 110],
            ['category' => 'spirit', 'type' => 'baileys',  'base_name' => 'Baileys',     'alcohol_percent' => 17.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Baileys', 'Sahne', 'Shot', 'Irish'],         'sort_order_base' => 120],
            ['category' => 'spirit', 'type' => 'malibu',   'base_name' => 'Malibu',      'alcohol_percent' => 21.0, 'is_alcoholic' => true, 'sizes' => [0.02, 0.04], 'search_terms' => ['Malibu', 'Kokos', 'Shot'],                   'sort_order_base' => 130],

            // ─── LONGDRINKS ───────────────────────────────────────────────────────
            ['category' => 'longdrink', 'type' => 'gin_tonic',      'base_name' => 'Gin Tonic',      'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Gin', 'Tonic', 'G&T', 'Longdrink'],                'sort_order_base' => 10],
            ['category' => 'longdrink', 'type' => 'vodka_lemon',    'base_name' => 'Wodka Lemon',    'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Wodka', 'Vodka', 'Lemon', 'Longdrink'],            'sort_order_base' => 20],
            ['category' => 'longdrink', 'type' => 'rum_cola',       'base_name' => 'Rum Cola',       'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Rum', 'Cola', 'Longdrink'],                        'sort_order_base' => 30],
            ['category' => 'longdrink', 'type' => 'whiskey_cola',   'base_name' => 'Whiskey Cola',   'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Whiskey', 'Whisky', 'Cola', 'Longdrink'],           'sort_order_base' => 40],
            ['category' => 'longdrink', 'type' => 'aperol_spritz',  'base_name' => 'Aperol Spritz',  'alcohol_percent' => 8.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Aperol', 'Spritz', 'Orange', 'Sekt'],               'sort_order_base' => 50],
            ['category' => 'longdrink', 'type' => 'hugo',           'base_name' => 'Hugo',           'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Hugo', 'Holunder', 'Sekt', 'Minze'],                'sort_order_base' => 60],
            ['category' => 'longdrink', 'type' => 'vodka_energy',   'base_name' => 'Vodka Energy',   'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Vodka', 'Wodka', 'Energy', 'Longdrink'],            'sort_order_base' => 70],
            ['category' => 'longdrink', 'type' => 'asbach_cola',    'base_name' => 'Asbach Cola',    'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Asbach', 'Weinbrand', 'Cola', 'Longdrink'],         'sort_order_base' => 80],
            ['category' => 'longdrink', 'type' => 'mariacron_cola', 'base_name' => 'Mariacron Cola', 'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Mariacron', 'Weinbrand', 'Cola', 'Longdrink'],      'sort_order_base' => 90],
            ['category' => 'longdrink', 'type' => 'korn_fanta',     'base_name' => 'Korn Fanta',     'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Korn', 'Fanta', 'Orange', 'Longdrink'],             'sort_order_base' => 100],
            ['category' => 'longdrink', 'type' => 'fanta_ficken',   'base_name' => 'Fanta F***en',   'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Fanta', 'Vodka', 'Longdrink'],                      'sort_order_base' => 110],
            ['category' => 'longdrink', 'type' => 'malibu_maracuja', 'base_name' => 'Malibu Maracuja', 'alcohol_percent' => 6.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Malibu', 'Maracuja', 'Kokos', 'Longdrink'],         'sort_order_base' => 120],
            ['category' => 'longdrink', 'type' => 'vodka_orange',   'base_name' => 'Vodka Orange',   'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Vodka', 'Wodka', 'Orange', 'Longdrink'],            'sort_order_base' => 130],
            ['category' => 'longdrink', 'type' => 'rum_orange',     'base_name' => 'Rum Orange',     'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Rum', 'Orange', 'Longdrink'],                       'sort_order_base' => 140],
            ['category' => 'longdrink', 'type' => 'gin_soda',       'base_name' => 'Gin Soda',       'alcohol_percent' => 7.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Gin', 'Soda', 'Wacholder', 'Longdrink'],            'sort_order_base' => 150],
            ['category' => 'longdrink', 'type' => 'malibu_orange',  'base_name' => 'Malibu Orange',  'alcohol_percent' => 6.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Malibu', 'Orange', 'Kokos', 'Longdrink'],           'sort_order_base' => 160],

            // ─── COCKTAILS ────────────────────────────────────────────────────────
            ['category' => 'cocktail', 'type' => 'mojito',               'base_name' => 'Mojito',              'alcohol_percent' => 12.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Mojito', 'Rum', 'Minze', 'Cocktail'],                  'sort_order_base' => 10],
            ['category' => 'cocktail', 'type' => 'caipirinha',           'base_name' => 'Caipirinha',          'alcohol_percent' => 14.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Caipirinha', 'Cachaca', 'Limette', 'Cocktail'],        'sort_order_base' => 20],
            ['category' => 'cocktail', 'type' => 'pina_colada',          'base_name' => 'Piña Colada',         'alcohol_percent' => 10.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Piña Colada', 'Pina Colada', 'Kokos', 'Cocktail'],    'sort_order_base' => 30],
            ['category' => 'cocktail', 'type' => 'cuba_libre',           'base_name' => 'Cuba Libre',          'alcohol_percent' => 12.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Cuba Libre', 'Rum', 'Cola', 'Cocktail'],               'sort_order_base' => 40],
            ['category' => 'cocktail', 'type' => 'margarita',            'base_name' => 'Margarita',           'alcohol_percent' => 13.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Margarita', 'Tequila', 'Limette', 'Cocktail'],         'sort_order_base' => 50],
            ['category' => 'cocktail', 'type' => 'long_island_iced_tea', 'base_name' => 'Long Island Iced Tea', 'alcohol_percent' => 18.0, 'is_alcoholic' => true, 'sizes' => [0.2, 0.3], 'search_terms' => ['Long Island', 'LIIT', 'Cocktail'],                    'sort_order_base' => 60],

            // ─── WASSER ───────────────────────────────────────────────────────────
            ['category' => 'water', 'type' => 'still_water',    'base_name' => 'Stilles Wasser', 'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -5, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Wasser', 'still', 'Mineralwasser'],             'sort_order_base' => 10],
            ['category' => 'water', 'type' => 'medium_water',   'base_name' => 'Medium',         'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -5, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Wasser', 'Medium', 'leicht sprudelnd'],         'sort_order_base' => 20],
            ['category' => 'water', 'type' => 'sparkling_water', 'base_name' => 'Sprudelwasser',  'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -5, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Wasser', 'Sprudel', 'Mineralwasser', 'sprudelnd'], 'sort_order_base' => 30],

            // ─── SOFTDRINKS ───────────────────────────────────────────────────────
            ['category' => 'softdrink', 'type' => 'cola',        'base_name' => 'Cola',        'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Cola', 'Softdrink'],                              'sort_order_base' => 10],
            ['category' => 'softdrink', 'type' => 'cola_zero',   'base_name' => 'Cola Zero',   'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Cola Zero', 'Cola', 'Light', 'zuckerfrei'],       'sort_order_base' => 20],
            ['category' => 'softdrink', 'type' => 'fanta',       'base_name' => 'Fanta',       'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Fanta', 'Orangenlimonade', 'Orange', 'Softdrink'], 'sort_order_base' => 30],
            ['category' => 'softdrink', 'type' => 'sprite',      'base_name' => 'Sprite',      'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Sprite', 'Zitronenlimonade', 'Limone'],           'sort_order_base' => 40],
            ['category' => 'softdrink', 'type' => 'spezi',       'base_name' => 'Spezi',       'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Spezi', 'Cola Orange', 'Mischgetränk'],           'sort_order_base' => 50],
            ['category' => 'softdrink', 'type' => 'tonic_water', 'base_name' => 'Tonic Water', 'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Tonic', 'Tonic Water', 'Bitter', 'Mixer'],        'sort_order_base' => 60],
            ['category' => 'softdrink', 'type' => 'ginger_ale',  'base_name' => 'Ginger Ale',  'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Ginger Ale', 'Ingwer', 'Mixer'],                 'sort_order_base' => 70],
            ['category' => 'softdrink', 'type' => 'ice_tea',     'base_name' => 'Eistee',      'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Eistee', 'Ice Tea', 'Tee', 'Pfirsich'],          'sort_order_base' => 80],
            ['category' => 'softdrink', 'type' => 'energy_drink', 'base_name' => 'Energy Drink', 'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Energy Drink', 'Energydrink', 'Koffein'],         'sort_order_base' => 90],
            ['category' => 'softdrink', 'type' => 'club_mate',   'base_name' => 'Club Mate',   'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.33, 0.5], 'search_terms' => ['Club Mate', 'Mate', 'Koffein', 'Softdrink'],      'sort_order_base' => 100],

            // ─── KAFFEE & TEE ─────────────────────────────────────────────────────
            ['category' => 'coffee', 'type' => 'coffee',     'base_name' => 'Kaffee',         'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2],       'search_terms' => ['Kaffee', 'Coffee', 'Filterkaffee'],            'sort_order_base' => 10],
            ['category' => 'coffee', 'type' => 'cappuccino', 'base_name' => 'Cappuccino',     'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.3], 'search_terms' => ['Cappuccino', 'Kaffee', 'Milch', 'Schaum'],    'sort_order_base' => 20],
            ['category' => 'coffee', 'type' => 'latte',      'base_name' => 'Latte Macchiato', 'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.3], 'search_terms' => ['Latte Macchiato', 'Latte', 'Kaffee', 'Milch'], 'sort_order_base' => 30],
            ['category' => 'coffee', 'type' => 'espresso',   'base_name' => 'Espresso',       'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.03],      'search_terms' => ['Espresso', 'Kaffee', 'stark', 'kurz'],        'sort_order_base' => 40],
            ['category' => 'coffee', 'type' => 'tea',        'base_name' => 'Tee',            'alcohol_percent' => 0.0, 'is_alcoholic' => false, 'negative_points' => -3, 'sizes' => [0.2, 0.3], 'search_terms' => ['Tee', 'Tea', 'Kräutertee', 'Früchtetee'],    'sort_order_base' => 50],
        ];

        $enNames = [
            'pils' => 'Pilsner',            'weizen' => 'Wheat Beer',
            'helles' => 'Helles Lager',       'kellerbier' => 'Kellerbier',
            'altbier' => 'Altbier',            'koelsch' => 'Kölsch',
            'radler' => 'Radler (Shandy)',    'bockbier' => 'Bock Beer',
            'dunkles' => 'Dark Beer',          'craft_beer' => 'Craft Beer',
            'beer_free' => 'Non-Alcoholic Beer', 'white_wine' => 'White Wine',
            'red_wine' => 'Red Wine',           'rose' => 'Rosé',
            'sekt' => 'Sparkling Wine',     'prosecco' => 'Prosecco',
            'weinschorle' => 'Wine Spritzer',      'gluehwein' => 'Mulled Wine',
            'champagne' => 'Champagne',          'vodka' => 'Vodka',
            'jaeger' => 'Jägermeister',       'korn' => 'Korn Schnapps',
            'tequila' => 'Tequila',            'rum' => 'Rum',
            'gin' => 'Gin',                'whiskey' => 'Whiskey',
            'bourbon' => 'Bourbon',            'sambuca' => 'Sambuca',
            'liqueur' => 'Liqueur',            'amaretto' => 'Amaretto',
            'baileys' => 'Baileys',            'malibu' => 'Malibu',
            'gin_tonic' => 'Gin & Tonic',        'vodka_lemon' => 'Vodka Lemon',
            'rum_cola' => 'Rum & Cola',         'whiskey_cola' => 'Whiskey & Cola',
            'aperol_spritz' => 'Aperol Spritz',      'hugo' => 'Hugo',
            'vodka_energy' => 'Vodka Energy',       'asbach_cola' => 'Asbach & Cola',
            'mariacron_cola' => 'Mariacron & Cola',   'korn_fanta' => 'Korn & Fanta',
            'fanta_ficken' => 'Fanta F***en',       'malibu_maracuja' => 'Malibu Passion Fruit',
            'vodka_orange' => 'Vodka Orange',       'rum_orange' => 'Rum & Orange',
            'gin_soda' => 'Gin & Soda',         'malibu_orange' => 'Malibu Orange',
            'mojito' => 'Mojito',             'caipirinha' => 'Caipirinha',
            'pina_colada' => 'Piña Colada',        'cuba_libre' => 'Cuba Libre',
            'margarita' => 'Margarita',          'long_island_iced_tea' => 'Long Island Iced Tea',
            'still_water' => 'Still Water',        'medium_water' => 'Medium Sparkling Water',
            'sparkling_water' => 'Sparkling Water',    'cola' => 'Cola',
            'cola_zero' => 'Cola Zero',          'fanta' => 'Fanta',
            'sprite' => 'Sprite',             'spezi' => 'Cola & Orange Mix',
            'tonic_water' => 'Tonic Water',        'ginger_ale' => 'Ginger Ale',
            'ice_tea' => 'Iced Tea',           'energy_drink' => 'Energy Drink',
            'club_mate' => 'Club Mate',          'coffee' => 'Coffee',
            'cappuccino' => 'Cappuccino',         'latte' => 'Latte Macchiato',
            'espresso' => 'Espresso',           'tea' => 'Tea',
        ];

        // ── Schritt 1: Typen upserten (1 Zeile pro Getränk) ─────────────────────
        $typeRows = [];
        foreach ($templates as $tpl) {
            $typeRows[] = [
                'category' => $tpl['category'],
                'type' => $tpl['type'],
                'display_name' => $tpl['base_name'],
                'display_name_en' => $enNames[$tpl['type']] ?? null,
                'alcohol_percent' => $tpl['alcohol_percent'],
                'is_alcoholic' => $tpl['is_alcoholic'],
                'negative_points' => $tpl['negative_points'] ?? null,
                'is_active' => true,
                'sort_order' => $tpl['sort_order_base'],
                'search_terms' => json_encode($tpl['search_terms']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DrinkCatalog::upsert(
            $typeRows,
            uniqueBy: ['type'],
            update: ['display_name', 'display_name_en', 'category', 'alcohol_percent', 'is_alcoholic', 'negative_points', 'is_active', 'sort_order', 'search_terms', 'updated_at'],
        );

        // ── Schritt 2: Größen pro Typ upserten ──────────────────────────────────
        foreach ($templates as $tpl) {
            $catalog = DrinkCatalog::where('type', $tpl['type'])->first();
            if (! $catalog) {
                continue;
            }

            $sizes = $tpl['sizes'];
            $count = count($sizes);
            $defaultIdx = (int) floor($count / 2); // mittlere Größe als Default

            $sizeRows = [];
            foreach ($sizes as $idx => $liter) {
                $sizeRows[] = [
                    'catalog_id' => $catalog->id,
                    'amount_liter' => $liter,
                    'is_default' => $idx === $defaultIdx ? 1 : 0,
                    'sort_order' => $idx,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DrinkCatalogSize::upsert(
                $sizeRows,
                uniqueBy: ['catalog_id', 'amount_liter'],
                update: ['is_default', 'sort_order', 'updated_at'],
            );
        }

        // ── Nicht mehr im Katalog enthaltene Typen deaktivieren ─────────────────
        $activeTypes = collect($templates)->pluck('type')->toArray();
        DrinkCatalog::whereNotIn('type', $activeTypes)->update(['is_active' => false]);
    }
}
