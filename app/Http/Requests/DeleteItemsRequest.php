<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Rules\RelationNotExist;
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
