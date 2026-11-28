<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'contact_name' => $this->contact_name,
            'company_name' => $this->company_name,
            'website' => $this->website,
            'enable_portal' => $this->enable_portal,
            'currency_id' => $this->currency_id,
            'company_id' => $this->company_id,
            'facebook_id' => $this->facebook_id,
            'google_id' => $this->google_id,
            'github_id' => $this->github_id,
            'formatted_created_at' => $this->formattedCreatedAt,
            'avatar' => $this->avatar,
            'prefix' => $this->prefix,
            'billing' => $this->when($this->relationLoaded('billingAddress'), function () {
                return new AddressResource($this->billingAddress);
            }),
            'shipping' => $this->when($this->relationLoaded('shippingAddress'), function () {
                return new AddressResource($this->shippingAddress);
            }),
            'fields' => $this->when($this->relationLoaded('fields'), function () {
                return CustomFieldValueResource::collection($this->fields);
            }),
            'company' => $this->when($this->relationLoaded('company'), function () {
                return new CompanyResource($this->company);
            }),
            'currency' => $this->when($this->relationLoaded('currency'), function () {
                return new CurrencyResource($this->currency);
            }),
        ];
    }
}
