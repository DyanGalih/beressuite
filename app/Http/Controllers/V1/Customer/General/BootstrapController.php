<?php

namespace App\Http\Controllers\V1\Customer\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\CustomerResource;
use App\Models\Currency;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BootstrapController extends Controller
{
    public function __invoke(Request $request): CustomerResource
    {
        $customer = Auth::guard('customer')->user();
        $menu = [];

        if ($customer) {
            foreach (config('crater.customer_menu', []) as $data) {
                $menu[] = [
                    'title' => $data['title'],
                    'link' => $data['link'],
                ];
            }
        }

        return (new CustomerResource($customer))
            ->additional(['meta' => [
                'menu' => $menu,
                'current_customer_currency' => Currency::find($customer->currency_id),
                'modules' => Module::where('enabled', true)->pluck('name'),
            ]]);
    }
}
