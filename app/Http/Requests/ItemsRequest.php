<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
            ],
            'price' => [
                'required',
            ],
            'unit_id' => [
                'nullable',
            ],
            'description' => [
                'nullable',
            ],
        ];
    }
}
