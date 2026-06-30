<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guest> */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'rsvp_status' => null,
            'app_access' => true,
            'drinks_access' => true,
        ];
    }

    public function withoutAppAccess(): static
    {
        return $this->state(['app_access' => false]);
    }

    public function withoutDrinksAccess(): static
    {
        return $this->state(['drinks_access' => false]);
    }
}
