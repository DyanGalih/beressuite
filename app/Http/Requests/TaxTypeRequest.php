<?php

namespace App\Http\Requests;

use App\Models\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                Rule::unique('tax_types')
                ->where('type', TaxType::TYPE_GENERAL)
                ->where('company_id', $this->header('company'))
            ],
            'percent' => [
                'required',
            ],
            'description' => [
                'nullable',
            ],
            'compound_tax' => [
                'nullable',
            ],
            'collective_tax' => [
                'nullable',
            ],
        ];

        if ($this->isMethod('PUT')) {
            $rules['name'] = [
                'required',
                Rule::unique('tax_types')
                    ->ignore($this->route('tax_type')->id)
                    ->where('type', TaxType::TYPE_GENERAL)
                    ->where('company_id', $this->header('company'))
            ];
        }

        return $rules;
    }

    public function getTaxTypePayload(): array
    {
        return collect($this->validated())
            ->merge([
                'company_id' => $this->header('company'),
                'type' => TaxType::TYPE_GENERAL
            ])
            ->toArray();
    }
}
