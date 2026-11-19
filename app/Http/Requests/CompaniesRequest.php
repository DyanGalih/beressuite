<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompaniesRequest extends FormRequest
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
                Rule::unique('companies'),
                'string'
            ],
            'currency' => [
                'required'
            ],
            'address.name' => [
                'nullable',
            ],
            'address.address_street_1' => [
                'nullable',
            ],
            'address.address_street_2' => [
                'nullable',
            ],
            'address.city' => [
                'nullable',
            ],
            'address.state' => [
                'nullable',
            ],
            'address.country_id' => [
                'required',
            ],
            'address.zip' => [
                'nullable',
            ],
            'address.phone' => [
                'nullable',
            ],
            'address.fax' => [
                'nullable',
            ],
        ];
    }

    public function getCompanyPayload(): array
    {
        return collect($this->validated())
            ->only([
                'name'
            ])
            ->merge([
                'owner_id' => $this->user()->id,
                'slug' => Str::slug($this->name)
            ])
            ->toArray();
    }
}
