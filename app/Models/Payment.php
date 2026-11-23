<?php

namespace App\Models;

use App\Jobs\GeneratePaymentPdfJob;
use App\Mail\SendPaymentMail;
use App\Services\SerialNumberFormatter;
use App\Traits\GeneratesPdfTrait;
use App\Traits\HasCustomFieldsTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Vinkla\Hashids\Facades\Hashids;

class Payment extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use GeneratesPdfTrait;
    use HasCustomFieldsTrait;

    public const PAYMENT_MODE_CHECK = 'CHECK';
    public const PAYMENT_MODE_OTHER = 'OTHER';
    public const PAYMENT_MODE_CASH = 'CASH';
    public const PAYMENT_MODE_CREDIT_CARD = 'CREDIT_CARD';
    public const PAYMENT_MODE_BANK_TRANSFER = 'BANK_TRANSFER';

    protected $guarded = ['id'];

    protected $appends = [
        'formattedCreatedAt',
        'formattedPaymentDate',
        'paymentPdfUrl',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'datetime:Y-m-d',
            'notes' => 'string',
            'exchange_rate' => 'float',
            'amount' => 'integer',
            'base_amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function ($payment) {
            GeneratePaymentPdfJob::dispatch($payment)->afterCommit();
        });

        static::updated(function ($payment) {
            GeneratePaymentPdfJob::dispatch($payment, true)->afterCommit();
        });
    }

    public function setSettingsAttribute($value): void
    {
        if ($value) {
            $this->attributes['settings'] = json_encode($value);
        }
    }

    public function getFormattedCreatedAtAttribute($value): string
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id) ?? 'Y-m-d';

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function getFormattedPaymentDateAttribute($value): string
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id) ?? 'Y-m-d';

        return Carbon::parse($this->payment_date)->format($dateFormat);
    }

    public function getPaymentPdfUrlAttribute(): string
    {
        return url('/payments/pdf/'.$this->unique_hash);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany('App\Models\EmailLog', 'mailable');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function sendPaymentData(array $data): array
    {
        $data['payment'] = $this->toArray();
        $data['user'] = $this->customer->toArray();
        $data['company'] = Company::find($this->company_id);
        $data['body'] = $this->getEmailBody($data['body']);
        $data['attach']['data'] = ($this->getEmailAttachmentSetting()) ? $this->getPDFData() : null;

        return $data;
    }

    public function send(array $data): array
    {
        $data = $this->sendPaymentData($data);

        Mail::to($data['to'])->send(new SendPaymentMail($data));

        return [
            'success' => true,
        ];
    }

    public static function createPayment(Request $request): self
    {
        return DB::transaction(function () use ($request) {
            $data = $request->getPaymentPayload();

            if ($request->invoice_id) {
                $invoice = Invoice::find($request->invoice_id);
                $invoice->subtractInvoicePayment($request->amount);
            }

            $payment = Payment::create($data);
            $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);

            $serial = (new SerialNumberFormatter())
                ->setModel($payment)
                ->setCompany($payment->company_id)
                ->setCustomer($payment->customer_id)
                ->setNextNumbers();

            $payment->sequence_number = $serial->nextSequenceNumber;
            $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
            $payment->save();

            $company_currency = CompanySetting::getSetting('currency', $request->header('company'));

            if ((string)$payment['currency_id'] !== $company_currency) {
                ExchangeRateLog::addExchangeRateLog($payment);
            }

            $customFields = $request->customFields;

            if ($customFields) {
                $payment->addCustomFields($customFields);
            }

            return Payment::with([
                'customer',
                'invoice',
                'paymentMethod',
                'fields'
            ])->find($payment->id);
        });
    }

    public function updatePayment(Request $request): self
    {
        return DB::transaction(function () use ($request) {
            $data = $request->getPaymentPayload();

            if ($request->invoice_id && (! $this->invoice_id || $this->invoice_id !== $request->invoice_id)) {
                $invoice = Invoice::find($request->invoice_id);
                $invoice->subtractInvoicePayment($request->amount);
            }

            if ($this->invoice_id && (! $request->invoice_id || $this->invoice_id !== $request->invoice_id)) {
                $invoice = Invoice::find($this->invoice_id);
                $invoice->addInvoicePayment($this->amount);
            }

            if ($this->invoice_id && $this->invoice_id === $request->invoice_id && $request->amount !== $this->amount) {
                $invoice = Invoice::find($this->invoice_id);
                $invoice->addInvoicePayment($this->amount);
                $invoice->subtractInvoicePayment($request->amount);
            }

            $serial = (new SerialNumberFormatter())
                ->setModel($this)
                ->setCompany($this->company_id)
                ->setCustomer($request->customer_id)
                ->setModelObject($this->id)
                ->setNextNumbers();

            $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;
            $this->update($data);

            $company_currency = CompanySetting::getSetting('currency', $request->header('company'));

            if ((string)$data['currency_id'] !== $company_currency) {
                ExchangeRateLog::addExchangeRateLog($this);
            }

            $customFields = $request->customFields;

            if ($customFields) {
                $this->updateCustomFields($customFields);
            }

            return Payment::with([
                'customer',
                'invoice',
                'paymentMethod',
            ])
                ->find($this->id);
        });
    }

    public static function deletePayments(array $ids): bool
    {
        foreach ($ids as $id) {
            $payment = Payment::find($id);

            if ($payment->invoice_id != null) {
                $invoice = Invoice::find($payment->invoice_id);
                $invoice->due_amount = ((int)$invoice->due_amount + (int)$payment->amount);

                if ($invoice->due_amount == $invoice->total) {
                    $invoice->paid_status = Invoice::STATUS_UNPAID;
                } else {
                    $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
                }

                $invoice->status = $invoice->getPreviousStatus();
                $invoice->save();
            }

            $payment->delete();
        }

        return true;
    }

    public function scopeWhereSearch(Builder $query, string $search): Builder
    {
        foreach (explode(' ', $search) as $term) {
            $query->whereHas('customer', function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('contact_name', 'LIKE', '%'.$term.'%')
                    ->orWhere('company_name', 'LIKE', '%'.$term.'%');
            });
        }

        return $query;
    }

    public function scopePaymentNumber(Builder $query, string $paymentNumber): Builder
    {
        return $query->where('payments.payment_number', 'LIKE', '%'.$paymentNumber.'%');
    }

    public function scopePaymentMethod(Builder $query, int|string $paymentMethodId): Builder
    {
        return $query->where('payments.payment_method_id', $paymentMethodId);
    }

    public function scopePaginateData(Builder $query, int|string|null $limit = null): LengthAwarePaginator|Collection
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('payment_number')) {
            $query->paymentNumber($filters->get('payment_number'));
        }

        if ($filters->get('payment_id')) {
            $query->wherePayment($filters->get('payment_id'));
        }

        if ($filters->get('payment_method_id')) {
            $query->paymentMethod($filters->get('payment_method_id'));
        }

        if ($filters->get('customer_id')) {
            $query->whereCustomer($filters->get('customer_id'));
        }

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->paymentsBetween($start, $end);
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'sequence_number';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'desc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function scopePaymentsBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween(
            'payments.payment_date',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWhereOrder(Builder $query, string $orderByField, string $orderBy): Builder
    {
        return $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWherePayment(Builder $query, int|string $payment_id): Builder
    {
        return $query->orWhere('id', $payment_id);
    }

    public function scopeWhereCompany(Builder $query): Builder
    {
        return $query->where('payments.company_id', request()->header('company'));
    }

    public function scopeWhereCustomer(Builder $query, int|string $customer_id): Builder
    {
        return $query->where('payments.customer_id', $customer_id);
    }

    public function getPDFData(): View|\Barryvdh\DomPDF\PDF
    {
        $company = Company::find($this->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);

        App::setLocale($locale);

        $logo = request()->has('preview') ? $company->logo : $company->logo_path;

        view()->share([
            'payment' => $this,
            'company_address' => $this->getCompanyAddress(),
            'billing_address' => $this->getCustomerBillingAddress(),
            'notes' => $this->getNotes(),
            'logo' => $logo ?? null,
        ]);

        if (request()->has('preview')) {
            return view('app.pdf.payment.payment');
        }

        return PDF::loadView('app.pdf.payment.payment');
    }

    public function getCompanyAddress(): string|false
    {
        if ($this->company && (! $this->company->address()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('payment_company_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getCustomerBillingAddress(): string|false
    {
        if ($this->customer && (! $this->customer->billingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('payment_from_customer_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getEmailAttachmentSetting(): bool
    {
        $paymentAsAttachment = CompanySetting::getSetting('payment_email_attachment', $this->company_id);

        if ($paymentAsAttachment == 'NO') {
            return false;
        }

        return true;
    }

    public function getNotes(): string
    {
        return $this->getFormattedString($this->notes);
    }

    public function getEmailBody(?string $body): string
    {
        $values = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $body = strtr($body ?? '', $values);

        return preg_replace('/{(.*?)}/', '', $body);
    }

    public function getExtraFields(): array
    {
        return [
            '{PAYMENT_DATE}' => $this->formattedPaymentDate,
            '{PAYMENT_MODE}' => $this->paymentMethod ? $this->paymentMethod->name : null,
            '{PAYMENT_NUMBER}' => $this->payment_number,
            '{PAYMENT_AMOUNT}' => $this->reference_number,
        ];
    }

    public static function generatePayment(Transaction $transaction): self
    {
        $invoice = Invoice::find($transaction->invoice_id);

        $serial = (new SerialNumberFormatter())
            ->setModel(new Payment())
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $data['payment_number'] = $serial->getNextNumber();
        $data['payment_date'] = Carbon::now()->format('y-m-d');
        $data['amount'] = $invoice->total;
        $data['invoice_id'] = $invoice->id;
        $data['payment_method_id'] = request()->payment_method_id;
        $data['customer_id'] = $invoice->customer_id;
        $data['exchange_rate'] = $invoice->exchange_rate;
        $data['base_amount'] = $data['amount'] * $data['exchange_rate'];
        $data['currency_id'] = $invoice->currency_id;
        $data['company_id'] = $invoice->company_id;
        $data['transaction_id'] = $transaction->id;

        $payment = Payment::create($data);
        $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);
        $payment->sequence_number = $serial->nextSequenceNumber;
        $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $payment->save();

        $invoice->subtractInvoicePayment($invoice->total);

        return $payment;
    }
}
