<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInteractionRequest;
use Illuminate\Http\JsonResponse;

class InteractionController extends Controller
{
    /**
     * Persist one authenticated interaction event without deduplication.
     */
    public function store(StoreInteractionRequest $request): JsonResponse
    {
        $interaction = $request->user()->interactions()->create($request->validated());

        return response()->json([
            'data' => [
                'id' => $interaction->id,
                'user_id' => $interaction->user_id,
                'post_id' => $interaction->post_id,
                'type' => $interaction->type,
                'created_at' => $interaction->created_at?->toISOString(),
            ],
        ], 201);
    }
}
