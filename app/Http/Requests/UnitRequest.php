<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $data = [
            'name' => [
                'required',
                Rule::unique('units')
                    ->where('company_id', $this->header('company')),
            ],
        ];

        if ($this->getMethod() == 'PUT') {
            $data['name'] = [
                'required',
                Rule::unique('units')
                    ->ignore($this->route('unit'), 'id')
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $data;
    }

    public function getUnitPayload(): array
    {
        return collect($this->validated())
            ->merge([
                'company_id' => $this->header('company')
            ])
            ->toArray();
    }
}
