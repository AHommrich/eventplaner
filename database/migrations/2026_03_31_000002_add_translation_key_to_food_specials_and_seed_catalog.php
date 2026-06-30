<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Default catalog: [German name => translation_key] */
    private array $catalog = [
        'Vegetarisch' => 'vegetarian',
        'Vegan' => 'vegan',
        'Glutenfrei' => 'gluten_free',
        'Laktosefrei' => 'lactose_free',
        'Nussallergie' => 'nut_allergy',
        'Kein Schweinefleisch' => 'no_pork',
        'Frutarier' => 'fruitarian',
        'Fruktose Intolerant' => 'fructose_intolerant',
        'Halal' => 'halal',
        'Koscher' => 'kosher',
        'Kein Fisch' => 'no_fish',
        'Diabetiker' => 'diabetic',
        'Kein Alkohol' => 'no_alcohol',
        'Schalentierallergie' => 'shellfish_allergy',
    ];

    public function up(): void
    {
        Schema::table('food_specials', function (Blueprint $table) {
            $table->string('translation_key')->nullable()->after('name');
        });

        $now = now();

        foreach ($this->catalog as $name => $key) {
            $existing = DB::table('food_specials')->where('name', $name)->first();

            if ($existing) {
                // attach key to the existing entry
                DB::table('food_specials')
                    ->where('id', $existing->id)
                    ->update(['translation_key' => $key, 'updated_at' => $now]);
            } else {
                // create new default entry
                DB::table('food_specials')->insert([
                    'name' => $name,
                    'translation_key' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('food_specials', function (Blueprint $table) {
            $table->dropColumn('translation_key');
        });
    }
};
