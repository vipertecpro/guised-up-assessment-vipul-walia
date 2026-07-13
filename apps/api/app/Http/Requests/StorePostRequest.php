<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may create a post.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the post creation validation rules.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:5000', 'regex:/\S/u'],
            'image_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
        ];
    }
}
