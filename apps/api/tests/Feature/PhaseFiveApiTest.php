<?php

namespace Tests\Feature;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseFiveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_four_phase_five_endpoints_require_authentication(): void
    {
        $this->postJson('/api/posts', [])->assertUnauthorized();
        $this->getJson('/api/feed')->assertUnauthorized();
        $this->getJson('/api/search?q=journey')->assertUnauthorized();
        $this->postJson('/api/interactions', [])->assertUnauthorized();
    }

    public function test_post_creation_validates_input_and_marks_successful_indexing_ready(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Http::fake([
            '*/documents/upsert' => Http::response([
                'document_id' => 'post-1',
                'status' => 'ready',
            ], 201),
        ]);

        $this->postJson('/api/posts', ['text' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('text');

        $this->postJson('/api/posts', [
            'text' => 'A valid post with an image URL that is too long.',
            'image_url' => 'https://example.com/'.str_repeat('a', 2030),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('image_url');

        $response = $this->postJson('/api/posts', [
            'text' => 'I learned something honest from a difficult conversation today.',
            'image_url' => 'https://example.com/photo.jpg',
        ])->assertCreated()
            ->assertJsonPath('data.embedding_status', Post::EMBEDDING_READY)
            ->assertJsonPath('data.user.id', 1)
            ->assertJsonMissingPath('data.vector_document_id')
            ->assertJsonMissingPath('data.embedding_error');

        $score = (float) $response->json('data.authenticity_score');
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(1, $score);
        $this->assertDatabaseHas('posts', [
            'id' => 1,
            'embedding_status' => Post::EMBEDDING_READY,
            'vector_document_id' => 'post-1',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8001/documents/upsert'
            && $request['document_id'] === 'post-1'
            && $request['metadata']['post_id'] === 1
            && $request['metadata']['author_id'] === 1);
    }

    public function test_post_creation_survives_embedding_failure_without_exposing_upstream_details(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Http::fake([
            '*/documents/upsert' => Http::response([
                'message' => 'secret upstream path /private/chroma and stack trace',
            ], 500),
        ]);

        $response = $this->postJson('/api/posts', [
            'text' => 'I still want this relational post to exist when indexing fails.',
        ])->assertCreated()
            ->assertJsonPath('data.embedding_status', Post::EMBEDDING_FAILED)
            ->assertJsonPath('warning', 'Semantic indexing failed and can be retried.');

        $this->assertDatabaseHas('posts', [
            'id' => 1,
            'embedding_status' => Post::EMBEDDING_FAILED,
            'embedding_error' => 'Semantic indexing failed.',
            'vector_document_id' => null,
        ]);
        $this->assertStringNotContainsString('private/chroma', $response->getContent());
        $this->assertStringNotContainsString('stack trace', $response->getContent());
    }

    public function test_interaction_logging_stores_each_event_and_rejects_invalid_types(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/interactions', [
            'post_id' => $post->id,
            'type' => Interaction::TYPE_REACTION,
        ])->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.post_id', $post->id)
            ->assertJsonPath('data.type', Interaction::TYPE_REACTION);

        $this->postJson('/api/interactions', [
            'post_id' => $post->id,
            'type' => 'like',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('interactions', 1);
    }

    public function test_search_hydrates_postgres_posts_preserves_order_and_ignores_stale_ids(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $first = Post::factory()->create(['text' => 'Authoritative first text']);
        $second = Post::factory()->create(['text' => 'Authoritative second text']);
        Http::fake([
            '*/search' => Http::response(['results' => [
                ['document_id' => "post-{$second->id}", 'score' => 0.93, 'metadata' => []],
                ['document_id' => 'post-999999', 'score' => 0.90, 'metadata' => []],
                ['document_id' => 'not-a-post', 'score' => 0.88, 'metadata' => []],
                ['document_id' => "post-{$first->id}", 'score' => 0.81, 'metadata' => []],
            ]]),
        ]);

        $this->getJson('/api/search?q=quiet%20journey')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.text', 'Authoritative second text')
            ->assertJsonPath('data.0.semantic_similarity', 0.93)
            ->assertJsonPath('data.1.id', $first->id);
    }

    public function test_search_returns_503_when_fastapi_is_unavailable(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Http::fake(['*/search' => Http::response([], 500)]);

        $this->getJson('/api/search?q=journey')
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Semantic search is temporarily unavailable.']);
    }

    public function test_relationship_depth_ranks_an_author_higher_and_platform_popularity_is_ignored(): void
    {
        CarbonImmutable::setTestNow('2026-07-13 12:00:00');
        $viewer = User::factory()->create();
        $closeAuthor = User::factory()->create();
        $popularAuthor = User::factory()->create();
        $history = Post::factory()->create([
            'user_id' => $closeAuthor->id,
            'created_at' => now()->subDays(50),
            'vector_document_id' => 'post-1',
            'embedding_status' => Post::EMBEDDING_READY,
        ]);
        $closeCandidate = Post::factory()->create([
            'user_id' => $closeAuthor->id,
            'authenticity_score' => 0.7,
            'created_at' => now(),
        ]);
        $popularCandidate = Post::factory()->create([
            'user_id' => $popularAuthor->id,
            'authenticity_score' => 0.7,
            'created_at' => now(),
        ]);
        Interaction::factory()->create([
            'user_id' => $viewer->id,
            'post_id' => $history->id,
            'type' => Interaction::TYPE_REPLY,
        ]);
        Interaction::factory()->count(25)->create([
            'post_id' => $popularCandidate->id,
            'type' => Interaction::TYPE_REPLY,
        ]);
        Sanctum::actingAs($viewer);
        Http::fake([
            '*/recommendations' => Http::response(['results' => [
                ['document_id' => "post-{$closeCandidate->id}", 'score' => 0.4, 'metadata' => []],
                ['document_id' => "post-{$popularCandidate->id}", 'score' => 0.4, 'metadata' => []],
            ]]),
        ]);

        $this->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $closeCandidate->id)
            ->assertJsonPath('data.0.ranking.relationship_depth', 1)
            ->assertJsonPath('data.1.id', $popularCandidate->id)
            ->assertJsonPath('data.1.ranking.relationship_depth', 0)
            ->assertJsonPath('meta.semantic_ranking_available', true);
    }

    public function test_feed_uses_deterministic_tie_breaking(): void
    {
        CarbonImmutable::setTestNow('2026-07-13 12:00:00');
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $olderId = Post::factory()->create([
            'user_id' => $author->id,
            'authenticity_score' => 0.5,
            'created_at' => now(),
        ]);
        $newerId = Post::factory()->create([
            'user_id' => $author->id,
            'authenticity_score' => 0.5,
            'created_at' => now(),
        ]);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newerId->id)
            ->assertJsonPath('data.1.id', $olderId->id);
        Http::assertNothingSent();
    }

    public function test_feed_gracefully_degrades_when_fastapi_fails(): void
    {
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'vector_document_id' => 'post-1',
            'embedding_status' => Post::EMBEDDING_READY,
        ]);
        Interaction::factory()->create(['user_id' => $viewer->id, 'post_id' => $post->id]);
        Post::factory()->create();
        Sanctum::actingAs($viewer);
        Http::fake(['*/recommendations' => Http::response([], 500)]);

        $this->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('meta.semantic_ranking_available', false)
            ->assertJsonPath('data.0.ranking.semantic_similarity', 0);
    }

    public function test_feed_paginates_after_ranking_at_exactly_twenty_posts(): void
    {
        CarbonImmutable::setTestNow('2026-07-13 12:00:00');
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        Post::factory()->count(21)->create([
            'user_id' => $author->id,
            'created_at' => now(),
        ]);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/feed?page=1')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.has_more_pages', true);

        $this->getJson('/api/feed?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more_pages', false);
    }
}
