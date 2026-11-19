<?php

namespace Crater\Http\Controllers\V1\Admin\General;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\CurrencyResource;
use Crater\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrenciesController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $currencies = Currency::latest()->get();

        return CurrencyResource::collection($currencies);
    }
}
