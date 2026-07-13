<?php

namespace Database\Seeders;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $referenceTime = CarbonImmutable::parse('2026-07-13 12:00:00', 'UTC');
        $users = [];

        foreach ([
            ['name' => 'Vipul Demo', 'email' => 'vipul@example.com'],
            ['name' => 'Maya Singh', 'email' => 'maya@example.com'],
            ['name' => 'Arjun Mehta', 'email' => 'arjun@example.com'],
        ] as $demoUser) {
            $user = User::query()->updateOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => $referenceTime,
                ],
            );

            $user->forceFill([
                'created_at' => $referenceTime,
                'updated_at' => $referenceTime,
            ])->saveQuietly();

            $users[$demoUser['email']] = $user;
        }

        $postDefinitions = [
            'vipul-train' => [
                'author' => 'vipul@example.com',
                'text' => 'I took the early train to Amritsar and spent most of the ride listening to strangers share travel stories.',
                'image_url' => null,
                'authenticity_score' => 0.9100,
                'created_at' => '2026-06-09 08:15:00',
            ],
            'vipul-debug' => [
                'author' => 'vipul@example.com',
                'text' => 'Spent Saturday untangling a stubborn deployment bug. The fix was one line, but the notes will save us next time.',
                'image_url' => null,
                'authenticity_score' => 0.8200,
                'created_at' => '2026-06-16 17:40:00',
            ],
            'vipul-chai' => [
                'author' => 'vipul@example.com',
                'text' => 'Made masala chai without measuring anything today, and somehow it tasted exactly like the cups we shared after college.',
                'image_url' => 'https://placehold.co/1200x800/png?text=Masala+Chai',
                'authenticity_score' => 0.8800,
                'created_at' => '2026-06-24 06:50:00',
            ],
            'vipul-friend' => [
                'author' => 'vipul@example.com',
                'text' => 'Called an old friend for a five-minute check-in and stayed on the phone for an hour. I needed that more than I knew.',
                'image_url' => null,
                'authenticity_score' => 0.9500,
                'created_at' => '2026-07-04 20:10:00',
            ],
            'vipul-refactor' => [
                'author' => 'vipul@example.com',
                'text' => 'The smallest refactor of the week removed three special cases and made the next feature feel obvious.',
                'image_url' => null,
                'authenticity_score' => 0.7600,
                'created_at' => '2026-07-11 15:25:00',
            ],
            'maya-jaipur' => [
                'author' => 'maya@example.com',
                'text' => 'Jaipur was loud, warm, and full of wrong turns. The unplanned tea stop ended up being my favorite part of the trip.',
                'image_url' => 'https://placehold.co/1200x800/png?text=Jaipur',
                'authenticity_score' => 0.9200,
                'created_at' => '2026-06-12 11:30:00',
            ],
            'maya-debug' => [
                'author' => 'maya@example.com',
                'text' => 'A teammate asked one patient question and helped me see the race condition I had stared at all morning.',
                'image_url' => null,
                'authenticity_score' => 0.8600,
                'created_at' => '2026-06-20 18:05:00',
            ],
            'maya-lunch' => [
                'author' => 'maya@example.com',
                'text' => 'Lunch was leftover dal, cold rice, and ten quiet minutes by the window. Not impressive, just genuinely good.',
                'image_url' => null,
                'authenticity_score' => 0.8400,
                'created_at' => '2026-06-27 13:10:00',
            ],
            'maya-walk' => [
                'author' => 'maya@example.com',
                'text' => 'Walked with my closest friend after months of rushed voice notes. Some conversations really need the same pavement.',
                'image_url' => null,
                'authenticity_score' => 0.9700,
                'created_at' => '2026-07-06 19:35:00',
            ],
            'maya-train' => [
                'author' => 'maya@example.com',
                'text' => 'The evening train ran late, so a family shared their oranges with me while we waited under the platform fan.',
                'image_url' => 'https://placehold.co/1200x800/png?text=Train+Journey',
                'authenticity_score' => 0.9000,
                'created_at' => '2026-07-12 21:15:00',
            ],
            'arjun-mountain' => [
                'author' => 'arjun@example.com',
                'text' => 'The mountain view disappeared behind rain, but the wet walk back and roadside noodles made the day memorable anyway.',
                'image_url' => 'https://placehold.co/1200x800/png?text=Rainy+Trail',
                'authenticity_score' => 0.8000,
                'created_at' => '2026-06-14 09:20:00',
            ],
            'arjun-api' => [
                'author' => 'arjun@example.com',
                'text' => 'Reworked an API response today so failure states are honest instead of looking like empty success. Small contract, big relief.',
                'image_url' => null,
                'authenticity_score' => 0.7300,
                'created_at' => '2026-06-22 16:45:00',
            ],
            'arjun-dal' => [
                'author' => 'arjun@example.com',
                'text' => 'My first attempt at my mother\'s dal was too smoky and a little salty. We still finished the pot together.',
                'image_url' => null,
                'authenticity_score' => 0.8900,
                'created_at' => '2026-06-29 20:30:00',
            ],
            'arjun-friend' => [
                'author' => 'arjun@example.com',
                'text' => 'Helped a friend move apartments and found a box of photos from our first jobs. We unpacked almost nothing after that.',
                'image_url' => null,
                'authenticity_score' => 0.9400,
                'created_at' => '2026-07-08 18:20:00',
            ],
            'arjun-deploy' => [
                'author' => 'arjun@example.com',
                'text' => 'A calm deploy, a clean error log, and enough time left to clear the desk before dinner. That is a good ending.',
                'image_url' => null,
                'authenticity_score' => 0.7800,
                'created_at' => '2026-07-13 07:00:00',
            ],
        ];

        $posts = [];

        foreach ($postDefinitions as $key => $definition) {
            $post = Post::query()->updateOrCreate(
                [
                    'user_id' => $users[$definition['author']]->id,
                    'text' => $definition['text'],
                ],
                [
                    'image_url' => $definition['image_url'],
                    'authenticity_score' => $definition['authenticity_score'],
                    'vector_document_id' => null,
                    'embedding_status' => Post::EMBEDDING_PENDING,
                    'embedding_error' => null,
                ],
            );

            $postTime = CarbonImmutable::parse($definition['created_at'], 'UTC');
            $post->forceFill([
                'created_at' => $postTime,
                'updated_at' => $postTime,
            ])->saveQuietly();

            $posts[$key] = $post;
        }

        $interactionDefinitions = [
            ['vipul@example.com', 'maya-jaipur', Interaction::TYPE_VIEW, '2026-06-13 08:00:00'],
            ['vipul@example.com', 'maya-jaipur', Interaction::TYPE_REACTION, '2026-06-13 08:04:00'],
            ['vipul@example.com', 'maya-jaipur', Interaction::TYPE_REPLY, '2026-06-13 08:12:00'],
            ['vipul@example.com', 'maya-debug', Interaction::TYPE_VIEW, '2026-06-21 09:15:00'],
            ['vipul@example.com', 'maya-debug', Interaction::TYPE_REACTION, '2026-06-21 09:18:00'],
            ['vipul@example.com', 'maya-lunch', Interaction::TYPE_VIEW, '2026-06-28 13:45:00'],
            ['vipul@example.com', 'maya-walk', Interaction::TYPE_VIEW, '2026-07-07 07:30:00'],
            ['vipul@example.com', 'maya-walk', Interaction::TYPE_REACTION, '2026-07-07 07:31:00'],
            ['vipul@example.com', 'maya-walk', Interaction::TYPE_REPLY, '2026-07-07 07:40:00'],
            ['vipul@example.com', 'arjun-mountain', Interaction::TYPE_VIEW, '2026-06-18 12:00:00'],
            ['vipul@example.com', 'arjun-api', Interaction::TYPE_REACTION, '2026-06-27 10:20:00'],
            ['maya@example.com', 'vipul-train', Interaction::TYPE_VIEW, '2026-06-15 06:30:00'],
            ['maya@example.com', 'vipul-chai', Interaction::TYPE_REACTION, '2026-06-26 17:00:00'],
            ['maya@example.com', 'vipul-friend', Interaction::TYPE_REPLY, '2026-07-07 20:10:00'],
            ['maya@example.com', 'arjun-dal', Interaction::TYPE_VIEW, '2026-07-05 11:25:00'],
            ['arjun@example.com', 'maya-jaipur', Interaction::TYPE_VIEW, '2026-06-14 14:00:00'],
            ['arjun@example.com', 'vipul-debug', Interaction::TYPE_REACTION, '2026-06-20 19:10:00'],
            ['arjun@example.com', 'vipul-friend', Interaction::TYPE_VIEW, '2026-07-09 08:05:00'],
        ];

        foreach ($interactionDefinitions as [$email, $postKey, $type, $createdAt]) {
            $interaction = Interaction::query()->updateOrCreate([
                'user_id' => $users[$email]->id,
                'post_id' => $posts[$postKey]->id,
                'type' => $type,
            ]);

            $interactionTime = CarbonImmutable::parse($createdAt, 'UTC');
            $interaction->forceFill([
                'created_at' => $interactionTime,
                'updated_at' => $interactionTime,
            ])->saveQuietly();
        }
    }
}
