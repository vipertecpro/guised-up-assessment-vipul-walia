<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
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
            'text' => fake()->randomElement([
                'I took the long way home and found a quiet street I had never noticed before.',
                'A small refactor removed more complexity than the feature originally added.',
                'Dinner was simple tonight, but sharing it made the whole day feel lighter.',
                'An old friend called at exactly the right moment, and we talked without watching the clock.',
            ]),
            'image_url' => null,
            'authenticity_score' => fake()->randomFloat(4, 0, 1),
            'vector_document_id' => null,
            'embedding_status' => Post::EMBEDDING_PENDING,
            'embedding_error' => null,
        ];
    }

    /**
     * Attach an explicitly requested stable placeholder image.
     */
    public function withImage(string $imageUrl = 'https://placehold.co/1200x800/png'): static
    {
        return $this->state(fn (array $attributes) => [
            'image_url' => $imageUrl,
        ]);
    }
}
