<?php

namespace App\Data\Customer;

use App\Data\Data;
use App\Http\Requests\CustomerRequest;

class CustomerData extends Data
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?int $currency_id = null,
        public ?string $password = null,
        public ?string $phone = null,
        public ?string $company_name = null,
        public ?string $contact_name = null,
        public ?string $website = null,
        public ?string $prefix = null,
        public ?bool $enable_portal = false,
        public ?array $shipping = null,
        public ?array $billing = null,
        public ?array $shippingAddress = null,
        public ?array $billingAddress = null,
        public ?array $customFields = [],
        public array $rawPayload = [],
    ) {
    }

    public static function fromRequest(CustomerRequest $request): self
    {
        $payload = $request->getCustomerPayload();

        $shippingAddress = null;
        if ($request->shipping && $request->hasAddress($request->shipping)) {
            $shippingAddress = $request->getShippingAddress();
        }

        $billingAddress = null;
        if ($request->billing && $request->hasAddress($request->billing)) {
            $billingAddress = $request->getBillingAddress();
        }

        return new self(
            name: (string) $request->input('name'),
            email: $request->input('email'),
            currency_id: $request->input('currency_id') ? (int) $request->input('currency_id') : null,
            password: $request->input('password'),
            phone: $request->input('phone'),
            company_name: $request->input('company_name'),
            contact_name: $request->input('contact_name'),
            website: $request->input('website'),
            prefix: $request->input('prefix'),
            enable_portal: (bool) $request->input('enable_portal', false),
            shipping: (array) $request->input('shipping'),
            billing: (array) $request->input('billing'),
            shippingAddress: $shippingAddress,
            billingAddress: $billingAddress,
            customFields: (array) ($request->input('customFields') ?? []),
            rawPayload: $payload,
        );
    }
}
