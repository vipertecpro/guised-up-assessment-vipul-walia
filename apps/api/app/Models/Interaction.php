<?php

namespace App\Models;

use Database\Factories\InteractionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'post_id', 'type'])]
class Interaction extends Model
{
    /** @use HasFactory<InteractionFactory> */
    use HasFactory;

    public const TYPE_VIEW = 'view';

    public const TYPE_REACTION = 'reaction';

    public const TYPE_REPLY = 'reply';

    /**
     * Get the user who performed the interaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the post targeted by the interaction.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
