<?php

namespace Database\Factories;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interaction>
 */
class InteractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'type' => fake()->randomElement([
                Interaction::TYPE_VIEW,
                Interaction::TYPE_REACTION,
                Interaction::TYPE_REPLY,
            ]),
        ];
    }
}
