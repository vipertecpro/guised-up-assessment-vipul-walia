<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'text',
    'image_url',
    'authenticity_score',
    'vector_document_id',
    'embedding_status',
    'embedding_error',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public const EMBEDDING_PENDING = 'pending';

    public const EMBEDDING_READY = 'ready';

    public const EMBEDDING_FAILED = 'failed';

    /**
     * Get the post author.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the interaction events recorded for the post.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authenticity_score' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
