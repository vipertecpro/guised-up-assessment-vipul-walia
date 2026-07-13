<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Services\EmbeddingsClient;
use App\Services\FeedRanker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    /**
     * Return a ranked page of twenty recent posts.
     */
    public function __invoke(
        Request $request,
        FeedRanker $ranker,
        EmbeddingsClient $embeddings,
    ): JsonResponse {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $page = (int) ($validated['page'] ?? 1);
        $ranking = $ranker->rank($request->user(), $page, $embeddings);

        return response()->json([
            'data' => PostResource::collection($ranking['posts'])->resolve(),
            'meta' => [
                'current_page' => $page,
                'per_page' => FeedRanker::PER_PAGE,
                'total' => $ranking['total'],
                'last_page' => $ranking['last_page'],
                'has_more_pages' => $page < $ranking['last_page'],
                'semantic_ranking_available' => $ranking['semantic_ranking_available'],
            ],
        ]);
    }
}
