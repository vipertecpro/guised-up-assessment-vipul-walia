<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\EmbeddingsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SearchController extends Controller
{
    /**
     * Return PostgreSQL posts in the semantic order supplied by FastAPI.
     */
    public function __invoke(Request $request, EmbeddingsClient $embeddings): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500', 'regex:/\S/u'],
        ]);

        try {
            $results = $embeddings->search(trim($validated['q']), 10);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Semantic search is temporarily unavailable.',
            ], 503);
        }

        $orderedScores = [];

        foreach ($results as $result) {
            if (preg_match('/^post-(\d+)$/', $result['document_id'], $matches) === 1) {
                $postId = (int) $matches[1];
                $orderedScores[$postId] ??= $result['score'];
            }
        }

        $postsById = Post::query()
            ->with('user:id,name')
            ->whereKey(array_keys($orderedScores))
            ->get()
            ->keyBy('id');

        $posts = collect($orderedScores)
            ->map(function (float $score, int $postId) use ($postsById): ?Post {
                $post = $postsById->get($postId);

                if ($post !== null) {
                    $post->setRelation('semantic_similarity', round($score, 4));
                }

                return $post;
            })
            ->filter()
            ->take(10)
            ->values();

        return response()->json([
            'data' => PostResource::collection($posts)->resolve(),
        ]);
    }
}
