<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\EmbeddingsClient;
use Illuminate\Console\Command;
use Throwable;

class IndexPosts extends Command
{
    /** @var string */
    protected $signature = 'app:index-posts {--force : Re-index every post}';

    /** @var string */
    protected $description = 'Index pending or failed posts in the embeddings service';

    /**
     * Index eligible posts and continue safely after individual failures.
     */
    public function handle(EmbeddingsClient $embeddings): int
    {
        $query = Post::query()->orderBy('id');

        if (! $this->option('force')) {
            $query->whereIn('embedding_status', [
                Post::EMBEDDING_PENDING,
                Post::EMBEDDING_FAILED,
            ]);
        }

        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        $query->each(function (Post $post) use ($embeddings, &$processed, &$succeeded, &$failed): void {
            $processed++;

            try {
                $embeddings->upsertPost($post);
                $post->forceFill([
                    'vector_document_id' => EmbeddingsClient::documentId($post->getKey()),
                    'embedding_status' => Post::EMBEDDING_READY,
                    'embedding_error' => null,
                ])->save();
                $succeeded++;
            } catch (Throwable) {
                $post->forceFill([
                    'vector_document_id' => null,
                    'embedding_status' => Post::EMBEDDING_FAILED,
                    'embedding_error' => 'Semantic indexing failed.',
                ])->save();
                $failed++;
            }
        });

        $this->line("Processed: {$processed}");
        $this->line("Succeeded: {$succeeded}");
        $this->line("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
