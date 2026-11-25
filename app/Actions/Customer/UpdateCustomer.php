<?php

namespace App\Actions\Customer;

use App\Actions\Action;
use App\Data\Customer\CustomerData;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class UpdateCustomer implements Action
{
    public function __invoke(Customer $customer, CustomerData $data, int|string|null $companyId = null): Customer|string
    {
        return DB::transaction(function () use ($customer, $data, $companyId): Customer|string {
            $condition = $customer->estimates()->exists() || $customer->invoices()->exists() || $customer->payments()->exists() || $customer->recurringInvoices()->exists();

            if (($customer->currency_id !== $data->currency_id) && $condition) {
                return 'you_cannot_edit_currency';
            }

            $payload = $data->rawPayload;
            $customer->update($payload);

            $customer->addresses()->delete();

            if ($data->shipping && ! empty($data->shippingAddress)) {
                $customer->addresses()->create($data->shippingAddress);
            }

            if ($data->billing && ! empty($data->billingAddress)) {
                $customer->addresses()->create($data->billingAddress);
            }

            if (! empty($data->customFields)) {
                $customer->updateCustomFields($data->customFields);
            }

            return Customer::with(['billingAddress', 'shippingAddress', 'fields'])->find($customer->id);
        });
    }
}
