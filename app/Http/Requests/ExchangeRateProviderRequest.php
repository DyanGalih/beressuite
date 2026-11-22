<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeRateProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'driver' => [
                'required',
            ],
            'key' => [
                $this->isMethod('post') ? 'required' : 'nullable',
            ],
            'currencies' => [
                'nullable',
            ],
            'currencies.*' => [
                'nullable',
            ],
            'driver_config' => [
                'nullable',
                'array',
            ],
            'driver_config.type' => [
                'nullable',
                'string',
            ],
            'driver_config.url' => [
                'nullable',
                'url',
                'regex:/^https:\/\//i',
                'required_if:driver_config.type,DEDICATED',
            ],
            'active' => [
                'nullable',
                'boolean',
            ],
        ];

        return $rules;
    }

    public function getExchangeRateProviderPayload()
    {
        $data = collect($this->validated())
            ->merge([
                'company_id' => $this->header('company'),
            ]);

        if (($this->isMethod('put') || $this->isMethod('patch')) && empty($this->key)) {
            $data = $data->forget('key');
        }

        return $data->toArray();
    }
}
