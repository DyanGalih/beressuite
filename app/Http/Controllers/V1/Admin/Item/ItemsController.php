<?php

namespace App\Http\Controllers\V1\Admin\Item;

use App\Actions\Item\CreateItem;
use App\Actions\Item\DeleteItems;
use App\Actions\Item\UpdateItem;
use App\Data\Item\ItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteItemsRequest;
use App\Http\Requests\ItemsRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\TaxType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemsController extends Controller
{
    /**
     * Retrieve a list of existing Items.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Item::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $items = Item::whereCompany()
            ->leftJoin('units', 'units.id', '=', 'items.unit_id')
            ->applyFilters($request->all())
            ->select('items.*', 'units.name as unit_name')
            ->latest()
            ->paginateData($limit);

        return (ItemResource::collection($items))
            ->additional(['meta' => [
                'tax_types' => TaxType::whereCompany()->latest()->get(),
                'item_total_count' => Item::whereCompany()->count(),
            ]]);
    }

    /**
     * Create Item.
     */
    public function store(ItemsRequest $request): ItemResource
    {
        $this->authorize('create', Item::class);

        $data = ItemData::fromRequest($request);
        $item = (new CreateItem())($data, $request->header('company'));

        return new ItemResource($item);
    }

    /**
     * get an existing Item.
     */
    public function show(Item $item): ItemResource
    {
        $this->authorize('view', $item);

        return new ItemResource($item);
    }

    /**
     * Update an existing Item.
     */
    public function update(ItemsRequest $request, Item $item): ItemResource
    {
        $this->authorize('update', $item);

        $data = ItemData::fromRequest($request);
        $item = (new UpdateItem())($item, $data, $request->header('company'));

        return new ItemResource($item);
    }

    /**
     * Delete a list of existing Items.
     */
    public function delete(DeleteItemsRequest $request): JsonResponse
    {
        $this->authorize('delete multiple items');

        (new DeleteItems())($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
