<?php

namespace Database\Factories;

use App\Models\DrinkCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DrinkCatalog> */
class DrinkCatalogFactory extends Factory
{
    protected $model = DrinkCatalog::class;

    public function definition(): array
    {
        return [
            'category' => 'beer',
            'type' => 'pils_'.fake()->unique()->randomNumber(5),
            'display_name' => 'Pils',
            'display_name_en' => null,
            'alcohol_percent' => 5.0,
            'is_alcoholic' => true,
            'negative_points' => 0,
            'is_active' => true,
            'sort_order' => 0,
            'search_terms' => [],
        ];
    }

    public function water(): static
    {
        return $this->state([
            'category' => 'water',
            'type' => 'water_'.fake()->unique()->randomNumber(5),
            'display_name' => 'Wasser',
            'alcohol_percent' => 0,
            'is_alcoholic' => false,
            'negative_points' => -5,
        ]);
    }

    public function spirit(): static
    {
        return $this->state([
            'category' => 'spirit',
            'type' => 'shot_'.fake()->unique()->randomNumber(5),
            'display_name' => 'Shot',
            'alcohol_percent' => 40.0,
            'is_alcoholic' => true,
        ]);
    }
}
