<?php

namespace App\Http\Controllers\V1\Customer\General;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerProfileRequest;
use App\Http\Resources\Customer\CustomerResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function updateProfile(Company $company, CustomerProfileRequest $request): CustomerResource
    {
        $customer = Auth::guard('customer')->user();

        $customer->update($request->validated());

        if (isset($request->is_customer_avatar_removed) && (bool) $request->is_customer_avatar_removed) {
            $customer->clearMediaCollection('customer_avatar');
        }
        if ($customer && $request->hasFile('customer_avatar')) {
            $customer->clearMediaCollection('customer_avatar');

            $customer->addMediaFromRequest('customer_avatar')
            ->toMediaCollection('customer_avatar');
        }

        if ($request->billing !== null) {
            $customer->shippingAddress()->delete();
            $customer->addresses()->create($request->getShippingAddress());
        }

        if ($request->shipping !== null) {
            $customer->billingAddress()->delete();
            $customer->addresses()->create($request->getBillingAddress());
        }

        $customer->load(['billingAddress.country', 'shippingAddress.country', 'fields', 'company', 'currency']);

        return new CustomerResource($customer);
    }

    public function getUser(Request $request): CustomerResource
    {
        $customer = Auth::guard('customer')->user();

        $customer?->load(['billingAddress.country', 'shippingAddress.country', 'fields', 'company', 'currency']);

        return new CustomerResource($customer);
    }
}
