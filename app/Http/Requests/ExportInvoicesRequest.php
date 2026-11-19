<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportInvoicesRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'paid_status' => [
                'nullable',
                'string',
                'in:PAID,UNPAID,PARTIALLY_PAID,all',
            ],
            'status' => [
                'nullable',
                'string',
            ],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],
            'from_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:to_date',
            ],
            'to_date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:from_date',
                'after_or_equal:from_date',
            ],
            'search' => [
                'nullable',
                'string',
            ],
        ];
    }
}
