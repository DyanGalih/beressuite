<?php

namespace App\Actions\Customer;

use App\Actions\Action;
use App\Data\Customer\CustomerData;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CreateCustomer implements Action
{
    public function __invoke(CustomerData $data, int|string|null $companyId = null): Customer
    {
        return DB::transaction(function () use ($data, $companyId): Customer {
            $payload = $data->rawPayload;

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? null;
            }

            $customer = Customer::create($payload);

            if ($data->shipping && ! empty($data->shippingAddress)) {
                $customer->addresses()->create($data->shippingAddress);
            }

            if ($data->billing && ! empty($data->billingAddress)) {
                $customer->addresses()->create($data->billingAddress);
            }

            if (! empty($data->customFields)) {
                $customer->addCustomFields($data->customFields);
            }

            return Customer::with(['billingAddress', 'shippingAddress', 'fields'])->find($customer->id);
        });
    }
}
