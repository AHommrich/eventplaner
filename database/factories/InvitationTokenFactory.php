<?php

namespace Database\Factories;

use App\Models\InvitationToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<InvitationToken> */
class InvitationTokenFactory extends Factory
{
    protected $model = InvitationToken::class;

    public function definition(): array
    {
        return [
            'token'    => Str::random(32),
            'group_id' => null,
            'guest_id' => null,
        ];
    }
}
