<?php

namespace App\Http\Controllers\V1\Admin\Customer;

use App\Actions\Customer\CreateCustomer;
use App\Actions\Customer\DeleteCustomers;
use App\Actions\Customer\UpdateCustomer;
use App\Data\Customer\CustomerData;
use App\Data\Customer\CustomerTableData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\DeleteCustomersRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class CustomersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $customers = Customer::whereCompany()
            ->with('currency')
            ->applyFilters($request->all())
            ->select(
                'customers.id',
                'customers.company_id',
                'customers.currency_id',
                'customers.name',
                'customers.contact_name',
                'customers.phone',
                'customers.email',
                'customers.created_at',
                DB::raw('sum(invoices.base_due_amount) as base_due_amount'),
                DB::raw('sum(invoices.due_amount) as due_amount'),
            )
            ->groupBy('customers.id')
            ->leftJoin('invoices', 'customers.id', '=', 'invoices.customer_id')
            ->paginateData($limit);

        $customers->through(fn (Customer $customer) => CustomerTableData::fromModel($customer));

        return (JsonResource::collection($customers))
            ->additional(['meta' => [
                'customer_total_count' => Customer::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CustomerRequest $request): CustomerResource
    {
        $this->authorize('create', Customer::class);

        $data = CustomerData::fromRequest($request);
        $customer = (new CreateCustomer())($data, $request->header('company'));

        return new CustomerResource($customer);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CustomerRequest $request, Customer $customer): CustomerResource|JsonResponse
    {
        $this->authorize('update', $customer);

        $data = CustomerData::fromRequest($request);
        $updated = (new UpdateCustomer())($customer, $data, $request->header('company'));

        if (is_string($updated)) {
            return respondJson('you_cannot_edit_currency', 'Cannot change currency once transactions created');
        }

        return new CustomerResource($updated);
    }

    /**
     * Remove a list of Customers along side all their resources (ie. Estimates, Invoices, Payments and Addresses)
     */
    public function delete(DeleteCustomersRequest $request): JsonResponse
    {
        $this->authorize('delete multiple customers');

        (new DeleteCustomers())($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
