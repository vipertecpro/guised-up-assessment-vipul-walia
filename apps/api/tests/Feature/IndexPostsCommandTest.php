<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IndexPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_indexes_pending_and_failed_posts_and_skips_ready_posts(): void
    {
        $pending = Post::factory()->create();
        $failed = Post::factory()->create(['embedding_status' => Post::EMBEDDING_FAILED]);
        $ready = Post::factory()->create([
            'embedding_status' => Post::EMBEDDING_READY,
            'vector_document_id' => 'post-3',
        ]);
        Http::fake(function ($request) {
            return Http::response([
                'document_id' => $request['document_id'],
                'status' => 'ready',
            ], 201);
        });

        $this->artisan('app:index-posts')
            ->expectsOutput('Processed: 2')
            ->expectsOutput('Succeeded: 2')
            ->expectsOutput('Failed: 0')
            ->assertSuccessful();

        $this->assertSame(Post::EMBEDDING_READY, $pending->refresh()->embedding_status);
        $this->assertSame('post-'.$pending->id, $pending->vector_document_id);
        $this->assertSame(Post::EMBEDDING_READY, $failed->refresh()->embedding_status);
        $this->assertSame(Post::EMBEDDING_READY, $ready->refresh()->embedding_status);
        Http::assertSentCount(2);
    }

    public function test_command_continues_after_failure_and_returns_non_zero(): void
    {
        Post::factory()->count(2)->create();
        Http::fakeSequence()
            ->push(['message' => 'internal secret'], 500)
            ->push(['document_id' => 'post-2', 'status' => 'ready'], 201);

        $this->artisan('app:index-posts')
            ->expectsOutput('Processed: 2')
            ->expectsOutput('Succeeded: 1')
            ->expectsOutput('Failed: 1')
            ->assertFailed();

        $this->assertDatabaseHas('posts', [
            'id' => 1,
            'embedding_status' => Post::EMBEDDING_FAILED,
            'embedding_error' => 'Semantic indexing failed.',
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => 2,
            'embedding_status' => Post::EMBEDDING_READY,
        ]);
    }
}
