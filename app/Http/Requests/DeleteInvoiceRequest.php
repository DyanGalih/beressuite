<?php

namespace Crater\Http\Requests;

use Crater\Models\Invoice;
use Crater\Rules\RelationNotExist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => [
                'required',
                'array',
            ],
            'ids.*' => [
                'required',
                Rule::exists('invoices', 'id')->where('company_id', $this->header('company')),
                new RelationNotExist(Invoice::class, 'payments'),
            ],
        ];
    }
}
