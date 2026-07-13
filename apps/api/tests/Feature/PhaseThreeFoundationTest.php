<?php

namespace Tests\Feature;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_create_valid_relational_records(): void
    {
        $post = Post::factory()->withImage()->create();
        $interaction = Interaction::factory()->create(['post_id' => $post->id]);

        $this->assertTrue($post->user->posts()->whereKey($post)->exists());
        $this->assertTrue($interaction->post->is($post));
        $this->assertTrue($interaction->user->interactions->contains($interaction));
        $this->assertTrue($post->interactions->contains($interaction));
        $this->assertGreaterThanOrEqual(0, (float) $post->authenticity_score);
        $this->assertLessThanOrEqual(1, (float) $post->authenticity_score);
        $this->assertSame(Post::EMBEDDING_PENDING, $post->embedding_status);
        $this->assertContains($interaction->type, [
            Interaction::TYPE_VIEW,
            Interaction::TYPE_REACTION,
            Interaction::TYPE_REPLY,
        ]);
        $this->assertSame('https://placehold.co/1200x800/png', $post->image_url);
    }

    public function test_demo_seeder_is_compact_repeatable_and_relationally_valid(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('posts', 15);
        $this->assertDatabaseCount('interactions', 18);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->assertSame(0, Post::query()->whereDoesntHave('user')->count());
        $this->assertSame(0, Interaction::query()->whereDoesntHave('user')->count());
        $this->assertSame(0, Interaction::query()->whereDoesntHave('post')->count());
        $this->assertSame(0, Post::query()->whereNotBetween('authenticity_score', [0, 1])->count());
        $this->assertSame(0, Post::query()->whereNotIn('embedding_status', [
            Post::EMBEDDING_PENDING,
            Post::EMBEDDING_READY,
            Post::EMBEDDING_FAILED,
        ])->count());
        $this->assertSame(0, Interaction::query()->whereNotIn('type', [
            Interaction::TYPE_VIEW,
            Interaction::TYPE_REACTION,
            Interaction::TYPE_REPLY,
        ])->count());

        $vipul = User::query()->where('email', 'vipul@example.com')->firstOrFail();
        $interactionWeights = [
            Interaction::TYPE_VIEW => 1,
            Interaction::TYPE_REACTION => 3,
            Interaction::TYPE_REPLY => 5,
        ];
        $depthByAuthor = $vipul->interactions()
            ->with('post.user')
            ->get()
            ->groupBy(fn (Interaction $interaction) => $interaction->post->user->email)
            ->map(fn ($interactions) => $interactions->sum(
                fn (Interaction $interaction) => $interactionWeights[$interaction->type],
            ));

        $this->assertGreaterThan(
            $depthByAuthor->get('arjun@example.com'),
            $depthByAuthor->get('maya@example.com'),
        );
    }

    public function test_demo_token_command_replaces_only_the_named_user_token(): void
    {
        $user = User::factory()->create(['email' => 'vipul@example.com']);
        $user->createToken('assessment-mobile');
        $user->createToken('keep-me');

        $this->artisan('app:issue-demo-token', ['email' => 'vipul@example.com'])
            ->expectsOutputToContain('Local assessment use only')
            ->assertSuccessful();

        $this->assertSame(1, $user->tokens()->where('name', 'assessment-mobile')->count());
        $this->assertSame(1, $user->tokens()->where('name', 'keep-me')->count());
        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_demo_token_command_fails_for_an_unknown_user(): void
    {
        $this->artisan('app:issue-demo-token', ['email' => 'missing@example.com'])
            ->expectsOutputToContain('No demo user exists')
            ->assertFailed();
    }
}
