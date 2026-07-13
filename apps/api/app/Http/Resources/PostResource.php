<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform a post into its public API representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'text' => $this->text,
            'image_url' => $this->image_url,
            'authenticity_score' => round((float) $this->authenticity_score, 4),
            'embedding_status' => $this->embedding_status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'ranking' => $this->when($this->resource->relationLoaded('ranking'), $this->ranking),
            'semantic_similarity' => $this->when(
                $this->resource->relationLoaded('semantic_similarity'),
                $this->semantic_similarity,
            ),
        ];
    }
}
