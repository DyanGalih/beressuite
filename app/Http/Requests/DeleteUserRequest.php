<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'users' => [
                'required',
                'array',
            ],
            'users.*' => [
                'required',
                Rule::exists('user_company', 'user_id')->where('company_id', $this->header('company')),
            ],
        ];
    }
}
