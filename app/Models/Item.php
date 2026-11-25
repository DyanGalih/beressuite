<?php

namespace App\Models;

use App\Actions\Item\CreateItem;
use App\Actions\Item\DeleteItems;
use App\Actions\Item\UpdateItem;
use App\Data\Item\ItemData;
use App\Http\Requests\ItemsRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class Item extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = [
        'formattedCreatedAt',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeWhereSearch(Builder $query, string $search): Builder
    {
        return $query->where('items.name', 'LIKE', '%'.$search.'%');
    }

    public function scopeWherePrice(Builder $query, int|string $price): Builder
    {
        return $query->where('items.price', $price);
    }

    public function scopeWhereUnit(Builder $query, int|string $unit_id): Builder
    {
        return $query->where('items.unit_id', $unit_id);
    }

    public function scopeWhereOrder(Builder $query, string $orderByField, string $orderBy): void
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereItem(Builder $query, int|string $item_id): void
    {
        $query->where('items.id', $item_id);
    }

    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('price')) {
            $query->wherePrice($filters->get('price'));
        }

        if ($filters->get('unit_id')) {
            $query->whereUnit($filters->get('unit_id'));
        }

        if ($filters->get('item_id')) {
            $query->whereItem($filters->get('item_id'));
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'name';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function scopePaginateData(Builder $query, int|string $limit): mixed
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function getFormattedCreatedAtAttribute($value): ?string
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', request()->header('company'));

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class)
            ->where('invoice_item_id', null)
            ->where('estimate_item_id', null);
    }

    public function scopeWhereCompany(Builder $query): void
    {
        $query->where('items.company_id', request()->header('company'));
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function estimateItems(): HasMany
    {
        return $this->hasMany(EstimateItem::class);
    }

    public static function createItem(Request $request): self
    {
        $data = $request instanceof ItemsRequest
            ? ItemData::fromRequest($request)
            : new ItemData(
                name: (string) $request->input('name'),
                price: $request->input('price'),
                unit_id: $request->input('unit_id') ? (int) $request->input('unit_id') : null,
                description: $request->input('description'),
                taxes: (array) ($request->input('taxes') ?? []),
                rawPayload: method_exists($request, 'validated') ? $request->validated() : $request->all(),
            );

        return (new CreateItem())($data, $request->header('company'));
    }

    public function updateItem(Request $request): self
    {
        $data = $request instanceof ItemsRequest
            ? ItemData::fromRequest($request)
            : new ItemData(
                name: (string) $request->input('name'),
                price: $request->input('price'),
                unit_id: $request->input('unit_id') ? (int) $request->input('unit_id') : null,
                description: $request->input('description'),
                taxes: (array) ($request->input('taxes') ?? []),
                rawPayload: method_exists($request, 'validated') ? $request->validated() : $request->all(),
            );

        return (new UpdateItem())($this, $data, $request->header('company'));
    }

    public static function deleteItems(array $ids): bool
    {
        return (new DeleteItems())($ids);
    }
}
