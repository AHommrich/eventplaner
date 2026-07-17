<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'author_user_id' => User::factory(),
            'author_name' => fake()->name(),
            'assignee_user_id' => null,
            'type' => 'note',
            'title' => fake()->sentence(3),
            'body' => fake()->optional()->sentence(),
            'is_done' => false,
            'done_at' => null,
        ];
    }

    public function todo(): static
    {
        return $this->state(fn () => ['type' => 'todo']);
    }

    public function done(): static
    {
        return $this->state(fn () => ['type' => 'todo', 'is_done' => true, 'done_at' => now()]);
    }
}
