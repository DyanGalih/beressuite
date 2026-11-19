<?php

namespace Crater\Http\Requests;

use Crater\Models\Item;
use Crater\Rules\RelationNotExist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteItemsRequest extends FormRequest
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
                Rule::exists('items', 'id')->where('company_id', $this->header('company')),
                new RelationNotExist(Item::class, 'invoiceItems'),
                new RelationNotExist(Item::class, 'estimateItems'),
                new RelationNotExist(Item::class, 'taxes'),
            ],
        ];
    }
}
