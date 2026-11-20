<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiTokenStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isOwner();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedAbilities = array_keys(config('api_tokens.abilities', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in($allowedAbilities)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
