<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Estimate;
use App\Traits\CalculatesDocumentTotals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstimatesRequest extends FormRequest
{
    use CalculatesDocumentTotals;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'estimate_date' => [
                'required',
            ],
            'expiry_date' => [
                'nullable',
            ],
            'customer_id' => [
                'required',
            ],
            'estimate_number' => [
                'nullable',
                Rule::unique('estimates')->where('company_id', $this->header('company'))
            ],
            'exchange_rate' => [
                'nullable'
            ],
            'discount' => [
                'required',
            ],
            'discount_val' => [
                'required',
            ],
            'sub_total' => [
                'required',
            ],
            'total' => [
                'required',
            ],
            'tax' => [
                'required',
            ],
            'template_name' => [
                'required'
            ],
            'items' => [
                'required',
                'array',
            ],
            'items.*.description' => [
                'nullable',
            ],
            'items.*' => [
                'required',
                'max:255',
            ],
            'items.*.name' => [
                'required',
            ],
            'items.*.quantity' => [
                'required',
            ],
            'items.*.price' => [
                'required',
            ],
        ];

        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $customer = Customer::find($this->customer_id);

        if ($companyCurrency && $customer) {
            if ((string)$customer->currency_id !== $companyCurrency) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            };
        }

        if ($this->isMethod('PUT')) {
            $rules['estimate_number'] = [
                'required',
                Rule::unique('estimates')
                    ->ignore($this->route('estimate')->id)
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $rules;
    }

    public function getEstimatePayload(): array
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;
        $currency = Customer::find($this->customer_id)->currency_id ?? $current_currency;

        $subTotal = $this->sub_total;
        $total = $this->total;
        $tax = $this->tax;
        $discountVal = $this->discount_val;

        if ($this->has('items') && is_array($this->items) && count($this->items) > 0) {
            $discountRate = $this->discount_type === 'percentage' ? ($this->discount ?? 0) : ($this->discount_val ?? $this->discount ?? 0);
            $calculated = $this->calculateDocumentTotals(
                $this->items,
                $this->discount_type,
                $discountRate,
                $this->taxes ?? []
            );
            $subTotal = $calculated['sub_total'];
            $total = $calculated['total'];
            $tax = $calculated['tax'];
            $discountVal = $this->discount_type === 'percentage' ? (int) round($calculated['discount']) : ($this->discount_val ?? 0);
        }

        return collect($this->except(['items', 'taxes', 'company_id', 'base_total', 'base_sub_total', 'base_tax']))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $this->has('estimateSend') ? Estimate::STATUS_SENT : Estimate::STATUS_DRAFT,
                'company_id' => $this->header('company'),
                'tax_per_item' => CompanySetting::getSetting('tax_per_item', $this->header('company')) ?? 'NO ',
                'discount_per_item' => CompanySetting::getSetting('discount_per_item', $this->header('company')) ?? 'NO',
                'sub_total' => $subTotal,
                'total' => $total,
                'tax' => $tax,
                'exchange_rate' => $exchange_rate,
                'base_discount_val' => $discountVal * $exchange_rate,
                'base_sub_total' => $subTotal * $exchange_rate,
                'base_total' => $total * $exchange_rate,
                'base_tax' => $tax * $exchange_rate,
                'currency_id' => $currency,
            ])
            ->toArray();
    }
}
