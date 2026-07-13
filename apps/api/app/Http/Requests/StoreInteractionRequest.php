<?php

namespace App\Http\Requests;

use App\Models\Interaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInteractionRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may log an interaction.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the interaction validation rules.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'type' => [
                'required',
                'string',
                Rule::in([
                    Interaction::TYPE_VIEW,
                    Interaction::TYPE_REACTION,
                    Interaction::TYPE_REPLY,
                ]),
            ],
        ];
    }
}
