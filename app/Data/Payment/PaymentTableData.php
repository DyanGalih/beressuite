<?php

namespace App\Data\Payment;

use App\Data\Common\CurrencySummaryData;
use App\Data\Common\CustomerSummaryData;
use App\Data\Common\InvoiceSummaryData;
use App\Data\Common\PaymentMethodSummaryData;
use App\Data\Data;
use App\Models\CompanySetting;
use App\Models\Payment;
use Carbon\Carbon;

class PaymentTableData extends Data
{
    public function __construct(
        public int $id,
        public string $payment_number,
        public ?string $payment_date,
        public ?string $formatted_payment_date,
        public float|int $amount,
        public ?int $payment_method_id,
        public ?PaymentMethodSummaryData $payment_method,
        public ?int $invoice_id,
        public ?InvoiceSummaryData $invoice,
        public ?int $customer_id,
        public ?CustomerSummaryData $customer,
        public ?int $currency_id,
        public ?CurrencySummaryData $currency,
        public ?string $payment_pdf_url = null,
        public ?string $unique_hash = null,
    ) {
    }

    public static function fromModel(Payment $payment): self
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $payment->company_id) ?? config('app.date_format', 'Y-m-d');

        $customer = $payment->relationLoaded('customer') && $payment->customer
            ? CustomerSummaryData::fromModel($payment->customer)
            : null;

        $currency = $payment->relationLoaded('currency') && $payment->currency
            ? CurrencySummaryData::fromModel($payment->currency)
            : ($customer?->currency ?? null);

        $paymentMethod = $payment->relationLoaded('paymentMethod') && $payment->paymentMethod
            ? PaymentMethodSummaryData::fromModel($payment->paymentMethod)
            : null;

        $invoice = $payment->relationLoaded('invoice') && $payment->invoice
            ? InvoiceSummaryData::fromModel($payment->invoice)
            : null;

        $paymentDate = $payment->payment_date instanceof \DateTimeInterface
            ? $payment->payment_date->format('Y-m-d')
            : ($payment->payment_date ? (string) $payment->payment_date : null);

        return new self(
            id: (int) $payment->id,
            payment_number: (string) $payment->payment_number,
            payment_date: $paymentDate,
            formatted_payment_date: $payment->payment_date ? Carbon::parse($payment->payment_date)->format($dateFormat) : null,
            amount: (float) ($payment->amount ?? 0),
            payment_method_id: $payment->payment_method_id ? (int) $payment->payment_method_id : null,
            payment_method: $paymentMethod,
            invoice_id: $payment->invoice_id ? (int) $payment->invoice_id : null,
            invoice: $invoice,
            customer_id: $payment->customer_id ? (int) $payment->customer_id : null,
            customer: $customer,
            currency_id: $payment->currency_id ? (int) $payment->currency_id : ($currency?->id ?? null),
            currency: $currency,
            payment_pdf_url: $payment->paymentPdfUrl,
            unique_hash: $payment->unique_hash,
        );
    }
}
