<?php

namespace App\Models;

use App\Actions\Expense\CreateExpense;
use App\Actions\Expense\DeleteExpenses;
use App\Actions\Expense\UpdateExpense;
use App\Data\Expense\ExpenseData;
use App\Http\Requests\ExpenseRequest;
use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Expense extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use HasCustomFieldsTrait;

    protected $guarded = ['id'];

    protected $appends = [
        'formattedExpenseDate',
        'formattedCreatedAt',
        'receipt',
        'receiptMeta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expense_date' => 'datetime:Y-m-d',
            'notes' => 'string',
            'exchange_rate' => 'float',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function getFormattedExpenseDateAttribute($value): ?string
    {
        if (! $this->expense_date) {
            return null;
        }

        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->expense_date)->format($dateFormat);
    }

    public function getFormattedCreatedAtAttribute($value): ?string
    {
        if (! $this->created_at) {
            return null;
        }

        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function getReceiptUrlAttribute($value): ?array
    {
        $media = $this->getFirstMedia('receipts');

        if ($media) {
            return [
                'url' => $media->getFullUrl(),
                'type' => $media->type,
            ];
        }

        return null;
    }

    public function getReceiptAttribute($value): ?string
    {
        $media = $this->getFirstMedia('receipts');

        if ($media) {
            return $media->getPath();
        }

        return null;
    }

    public function getReceiptMetaAttribute($value): mixed
    {
        $media = $this->getFirstMedia('receipts');

        if ($media) {
            return $media;
        }

        return null;
    }

    public function scopeExpensesBetween(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween(
            'expenses.expense_date',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWhereCategoryName(Builder $query, $search): Builder
    {
        foreach (explode(' ', $search) as $term) {
            $query->whereHas('category', function ($subQuery) use ($term) {
                $subQuery->where('name', 'LIKE', '%'.$term.'%');
            });
        }

        return $query;
    }

    public function scopeWhereNotes(Builder $query, $search): Builder
    {
        return $query->where('notes', 'LIKE', '%'.$search.'%');
    }

    public function scopeWhereCategory(Builder $query, $categoryId): Builder
    {
        return $query->where('expenses.expense_category_id', $categoryId);
    }

    public function scopeWhereUser(Builder $query, $customer_id): Builder
    {
        return $query->where('expenses.customer_id', $customer_id);
    }

    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);

        if ($filters->get('expense_category_id')) {
            $query->whereCategory($filters->get('expense_category_id'));
        }

        if ($filters->get('customer_id')) {
            $query->whereUser($filters->get('customer_id'));
        }

        if ($filters->get('expense_id')) {
            $query->whereExpense($filters->get('expense_id'));
        }

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->expensesBetween($start, $end);
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'expense_date';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }
    }

    public function scopeWhereExpense(Builder $query, $expense_id): Builder
    {
        return $query->where('expenses.id', $expense_id);
    }

    public function scopeWhereSearch(Builder $query, $search): Builder
    {
        foreach (explode(' ', $search) as $term) {
            $query->where(function ($subQuery) use ($term) {
                $subQuery->whereHas('category', function ($categoryQuery) use ($term) {
                    $categoryQuery->where('name', 'LIKE', '%'.$term.'%');
                })
                ->orWhere('expenses.notes', 'LIKE', '%'.$term.'%');
            });
        }

        return $query;
    }

    public function scopeWhereOrder(Builder $query, $orderByField, $orderBy): Builder
    {
        return $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereCompany(Builder $query): Builder
    {
        return $query->where('expenses.company_id', request()->header('company'));
    }

    public function scopeWhereCompanyId(Builder $query, $company): Builder
    {
        return $query->where('expenses.company_id', $company);
    }

    public function scopePaginateData(Builder $query, int|string|null $limit = null): mixed
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function scopeExpensesAttributes(Builder $query): Builder
    {
        return $query->select(
            DB::raw('
                count(*) as expenses_count,
                sum(base_amount) as total_amount,
                expense_category_id')
        )
            ->groupBy('expense_category_id');
    }

    public static function createExpense(Request $request): self
    {
        $data = $request instanceof ExpenseRequest
            ? ExpenseData::fromRequest($request)
            : new ExpenseData(
                expense_category_id: (int) $request->input('expense_category_id'),
                expense_date: (string) $request->input('expense_date'),
                amount: (float) $request->input('amount'),
                customer_id: $request->input('customer_id') ? (int) $request->input('customer_id') : null,
                payment_method_id: $request->input('payment_method_id') ? (int) $request->input('payment_method_id') : null,
                notes: $request->input('notes'),
                exchange_rate: $request->input('exchange_rate'),
                currency_id: $request->input('currency_id') ? (int) $request->input('currency_id') : null,
                customFields: $request->customFields,
                is_attachment_receipt_removed: (bool) $request->input('is_attachment_receipt_removed', false),
                rawPayload: method_exists($request, 'getExpensePayload') ? $request->getExpensePayload() : $request->all(),
            );

        return (new CreateExpense())($data, $request, $request->header('company'));
    }

    public function updateExpense(Request $request): bool
    {
        $data = $request instanceof ExpenseRequest
            ? ExpenseData::fromRequest($request)
            : new ExpenseData(
                expense_category_id: (int) $request->input('expense_category_id'),
                expense_date: (string) $request->input('expense_date'),
                amount: (float) $request->input('amount'),
                customer_id: $request->input('customer_id') ? (int) $request->input('customer_id') : null,
                payment_method_id: $request->input('payment_method_id') ? (int) $request->input('payment_method_id') : null,
                notes: $request->input('notes'),
                exchange_rate: $request->input('exchange_rate'),
                currency_id: $request->input('currency_id') ? (int) $request->input('currency_id') : null,
                customFields: $request->customFields,
                is_attachment_receipt_removed: (bool) $request->input('is_attachment_receipt_removed', false),
                rawPayload: method_exists($request, 'getExpensePayload') ? $request->getExpensePayload() : $request->all(),
            );

        (new UpdateExpense())($this, $data, $request, $request->header('company'));

        return true;
    }

    public static function deleteExpenses(array $ids): bool
    {
        return (new DeleteExpenses())($ids);
    }
}
