<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name'     => 'Familie ' . fake()->lastName(),
        ];
    }
}
