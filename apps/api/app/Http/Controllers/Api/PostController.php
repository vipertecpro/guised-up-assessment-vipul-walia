<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\AuthenticityScorer;
use App\Services\EmbeddingsClient;
use Illuminate\Http\JsonResponse;
use Throwable;

class PostController extends Controller
{
    /**
     * Create and synchronously attempt to semantically index a post.
     */
    public function store(
        StorePostRequest $request,
        AuthenticityScorer $scorer,
        EmbeddingsClient $embeddings,
    ): JsonResponse {
        $validated = $request->validated();
        $post = $request->user()->posts()->create([
            'text' => trim($validated['text']),
            'image_url' => $validated['image_url'] ?? null,
            'authenticity_score' => $scorer->score(
                $validated['text'],
                isset($validated['image_url']),
            ),
            'embedding_status' => Post::EMBEDDING_PENDING,
            'vector_document_id' => null,
            'embedding_error' => null,
        ]);

        $warning = null;

        try {
            $embeddings->upsertPost($post);
            $post->forceFill([
                'vector_document_id' => EmbeddingsClient::documentId($post->getKey()),
                'embedding_status' => Post::EMBEDDING_READY,
                'embedding_error' => null,
            ])->save();
        } catch (Throwable) {
            $post->forceFill([
                'vector_document_id' => null,
                'embedding_status' => Post::EMBEDDING_FAILED,
                'embedding_error' => 'Semantic indexing failed.',
            ])->save();
            $warning = 'Semantic indexing failed and can be retried.';
        }

        $response = ['data' => (new PostResource($post->load('user:id,name')))->resolve()];

        if ($warning !== null) {
            $response['warning'] = $warning;
        }

        return response()->json($response, 201);
    }
}
