<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class EmbeddingsClient
{
    /**
     * Index one authoritative PostgreSQL post under its deterministic vector ID.
     */
    public function upsertPost(Post $post): void
    {
        $documentId = self::documentId($post->getKey());
        $response = $this->request()->post('/documents/upsert', [
            'document_id' => $documentId,
            'text' => $post->text,
            'metadata' => [
                'post_id' => $post->getKey(),
                'author_id' => $post->user_id,
                'created_at' => $post->created_at->toISOString(),
            ],
        ]);

        if (! $response->successful()
            || $response->json('document_id') !== $documentId
            || $response->json('status') !== 'ready') {
            throw new RuntimeException('The embeddings service returned an invalid upsert response.');
        }
    }

    /**
     * Search vector documents in semantic order.
     *
     * @return list<array{document_id: string, score: float}>
     */
    public function search(string $query, int $limit = 10, array $excludeIds = []): array
    {
        return $this->results('/search', [
            'query' => $query,
            'limit' => max(1, min(10, $limit)),
            'exclude_document_ids' => array_values($excludeIds),
        ]);
    }

    /**
     * Recommend vector documents from a compact set of demonstrated interests.
     *
     * @param  list<string>  $seedDocumentIds
     * @param  list<string>  $excludeIds
     * @return list<array{document_id: string, score: float}>
     */
    public function recommend(array $seedDocumentIds, int $limit, array $excludeIds = []): array
    {
        if ($seedDocumentIds === []) {
            return [];
        }

        return $this->results('/recommendations', [
            'seed_document_ids' => array_values($seedDocumentIds),
            'limit' => max(1, min(50, $limit)),
            'exclude_document_ids' => array_values($excludeIds),
        ]);
    }

    /**
     * Build the stable Chroma document identifier for a relational post.
     */
    public static function documentId(int|string $postId): string
    {
        return 'post-'.$postId;
    }

    /**
     * @return list<array{document_id: string, score: float}>
     */
    private function results(string $path, array $payload): array
    {
        $response = $this->request()->post($path, $payload);

        if (! $response->successful() || ! is_array($response->json('results'))) {
            throw new RuntimeException('The embeddings service returned an invalid search response.');
        }

        $results = [];

        foreach ($response->json('results') as $result) {
            if (! is_array($result)
                || ! isset($result['document_id'], $result['score'])
                || ! is_string($result['document_id'])
                || ! is_numeric($result['score'])) {
                throw new RuntimeException('The embeddings service returned an invalid result item.');
            }

            $results[] = [
                'document_id' => $result['document_id'],
                'score' => max(0.0, min(1.0, (float) $result['score'])),
            ];
        }

        return $results;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.embeddings.url'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.embeddings.timeout'))
            ->connectTimeout((int) config('services.embeddings.connect_timeout'));
    }
}
