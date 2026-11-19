<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    public const TYPE_GENERAL = 'GENERAL';
    public const TYPE_MODULE = 'MODULE';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'use_test_env' => 'boolean',
        ];
    }

    public function setSettingsAttribute($value): void
    {
        $this->attributes['settings'] = json_encode($value);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWhereCompanyId(Builder $query, $id): Builder
    {
        return $query->where('company_id', $id);
    }

    public function scopeWhereCompany(Builder $query): Builder
    {
        return $query->where('company_id', request()->header('company'));
    }

    public function scopeWherePaymentMethod(Builder $query, $payment_id): Builder
    {
        return $query->orWhere('id', $payment_id);
    }

    public function scopeWhereSearch(Builder $query, $search): Builder
    {
        return $query->where('name', 'LIKE', '%'.$search.'%');
    }

    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);

        if ($filters->get('method_id')) {
            $query->wherePaymentMethod($filters->get('method_id'));
        }

        if ($filters->get('company_id')) {
            $query->whereCompany($filters->get('company_id'));
        }

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }
    }

    public function scopePaginateData(Builder $query, int|string|null $limit = null): mixed
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public static function createPaymentMethod($request): self
    {
        $data = $request->getPaymentMethodPayload();

        $paymentMethod = self::create($data);

        return $paymentMethod;
    }

    public static function getSettings($id): ?array
    {
        return PaymentMethod::find($id)?->settings;
    }
}
