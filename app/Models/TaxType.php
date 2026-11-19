<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxType extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    public const TYPE_GENERAL = 'GENERAL';
    public const TYPE_MODULE = 'MODULE';

    protected function casts(): array
    {
        return [
            'percent' => 'float',
            'compound_tax' => 'boolean',
        ];
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWhereCompany(Builder $query, int|string|null $company_id = null): Builder
    {
        return $query->where('company_id', $company_id ?? request()->header('company'));
    }

    public function scopeWhereTaxType(Builder $query, int|string $tax_type_id): Builder
    {
        return $query->orWhere('id', $tax_type_id);
    }

    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);

        if ($filters->get('tax_type_id')) {
            $query->whereTaxType($filters->get('tax_type_id'));
        }

        if ($filters->get('company_id')) {
            $query->whereCompany($filters->get('company_id'));
        }

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'payment_number';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function scopeWhereOrder(Builder $query, string $orderByField, string $orderBy): Builder
    {
        return $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'LIKE', '%'.$search.'%');
    }

    public function scopePaginateData(Builder $query, int|string $limit): \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate((int) $limit);
    }
}
