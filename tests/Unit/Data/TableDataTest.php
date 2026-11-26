<?php

use App\Data\Common\CurrencySummaryData;
use App\Data\Common\CustomerSummaryData;
use App\Data\Common\UnitSummaryData;
use App\Data\Customer\CustomerTableData;
use App\Data\Estimate\EstimateTableData;
use App\Data\Expense\ExpenseTableData;
use App\Data\Invoice\InvoiceTableData;
use App\Data\Item\ItemTableData;
use App\Data\Payment\PaymentTableData;
use App\Data\RecurringInvoice\RecurringInvoiceTableData;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RecurringInvoice;
use App\Models\Unit;

test('CurrencySummaryData maps model correctly', function () {
    $currency = new Currency([
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'precision' => 2,
        'thousand_separator' => ',',
        'decimal_separator' => '.',
        'swap_currency_symbol' => false,
        'exchange_rate' => 1.0,
    ]);
    $currency->id = 5;

    $dto = CurrencySummaryData::fromModel($currency);

    expect($dto)->not->toBeNull()
        ->and($dto->id)->toBe(5)
        ->and($dto->name)->toBe('US Dollar')
        ->and($dto->code)->toBe('USD')
        ->and($dto->symbol)->toBe('$')
        ->and($dto->precision)->toBe(2);
});

test('CustomerSummaryData maps model with eager-loaded currency', function () {
    $currency = new Currency(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $currency->id = 1;

    $customer = new Customer(['name' => 'Acme Corp', 'contact_name' => 'Alice', 'email' => 'alice@acme.corp']);
    $customer->id = 10;
    $customer->currency_id = 1;
    $customer->setRelation('currency', $currency);

    $dto = CustomerSummaryData::fromModel($customer);

    expect($dto)->not->toBeNull()
        ->and($dto->id)->toBe(10)
        ->and($dto->name)->toBe('Acme Corp')
        ->and($dto->contact_name)->toBe('Alice')
        ->and($dto->email)->toBe('alice@acme.corp')
        ->and($dto->currency)->not->toBeNull()
        ->and($dto->currency->code)->toBe('USD');
});

test('InvoiceTableData maps minimal table attributes and excludes heavy items', function () {
    $currency = new Currency(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $currency->id = 1;

    $customer = new Customer(['name' => 'Acme Corp']);
    $customer->id = 10;
    $customer->setRelation('currency', $currency);

    $invoice = new Invoice([
        'invoice_number' => 'INV-0001',
        'invoice_date' => '2026-01-15',
        'due_date' => '2026-02-15',
        'status' => 'DRAFT',
        'paid_status' => 'UNPAID',
        'total' => 50000,
        'due_amount' => 50000,
        'overdue' => false,
        'company_id' => 1,
    ]);
    $invoice->id = 101;
    $invoice->customer_id = 10;
    $invoice->currency_id = 1;
    $invoice->setRelation('customer', $customer);
    $invoice->setRelation('currency', $currency);

    $dto = InvoiceTableData::fromModel($invoice);

    expect($dto->id)->toBe(101)
        ->and($dto->invoice_number)->toBe('INV-0001')
        ->and($dto->status)->toBe('DRAFT')
        ->and($dto->total)->toBe(50000.0)
        ->and($dto->customer->name)->toBe('Acme Corp')
        ->and($dto->currency->code)->toBe('USD')
        ->and($dto->toArray())->not->toHaveKey('items')
        ->and($dto->toArray())->not->toHaveKey('taxes');
});

test('EstimateTableData maps minimal table attributes', function () {
    $currency = new Currency(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $currency->id = 1;

    $customer = new Customer(['name' => 'Beta Corp']);
    $customer->id = 10;
    $customer->setRelation('currency', $currency);

    $estimate = new Estimate([
        'estimate_number' => 'EST-0001',
        'estimate_date' => '2026-01-10',
        'expiry_date' => '2026-02-10',
        'status' => 'SENT',
        'total' => 12500,
        'company_id' => 1,
    ]);
    $estimate->id = 201;
    $estimate->customer_id = 10;
    $estimate->currency_id = 1;
    $estimate->setRelation('customer', $customer);
    $estimate->setRelation('currency', $currency);

    $dto = EstimateTableData::fromModel($estimate);

    expect($dto->id)->toBe(201)
        ->and($dto->estimate_number)->toBe('EST-0001')
        ->and($dto->status)->toBe('SENT')
        ->and($dto->total)->toBe(12500.0)
        ->and($dto->customer->name)->toBe('Beta Corp')
        ->and($dto->toArray())->not->toHaveKey('items');
});

test('PaymentTableData maps minimal payment attributes with relations', function () {
    $currency = new Currency(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $currency->id = 1;

    $customer = new Customer(['name' => 'Charlie LLC']);
    $customer->id = 10;

    $method = new PaymentMethod(['name' => 'Bank Transfer']);
    $method->id = 3;

    $invoice = new Invoice(['invoice_number' => 'INV-0001']);
    $invoice->id = 101;

    $payment = new Payment([
        'payment_number' => 'PAY-0001',
        'payment_date' => '2026-01-20',
        'amount' => 5000,
        'company_id' => 1,
    ]);
    $payment->id = 301;
    $payment->customer_id = 10;
    $payment->currency_id = 1;
    $payment->payment_method_id = 3;
    $payment->invoice_id = 101;
    $payment->setRelation('customer', $customer);
    $payment->setRelation('currency', $currency);
    $payment->setRelation('paymentMethod', $method);
    $payment->setRelation('invoice', $invoice);

    $dto = PaymentTableData::fromModel($payment);

    expect($dto->id)->toBe(301)
        ->and($dto->payment_number)->toBe('PAY-0001')
        ->and($dto->amount)->toBe(5000.0)
        ->and($dto->customer->name)->toBe('Charlie LLC')
        ->and($dto->payment_method->name)->toBe('Bank Transfer')
        ->and($dto->invoice->invoice_number)->toBe('INV-0001');
});

test('ExpenseTableData maps minimal expense attributes with category and notes', function () {
    $category = new ExpenseCategory(['name' => 'Office Supplies']);
    $category->id = 4;

    $expense = new Expense([
        'expense_date' => '2026-01-18',
        'amount' => 1500,
        'notes' => 'Office stationery and printer ink',
        'company_id' => 1,
    ]);
    $expense->id = 401;
    $expense->expense_category_id = 4;
    $expense->setRelation('category', $category);

    $dto = ExpenseTableData::fromModel($expense);

    expect($dto->id)->toBe(401)
        ->and($dto->amount)->toBe(1500.0)
        ->and($dto->notes)->toBe('Office stationery and printer ink')
        ->and($dto->expense_category->name)->toBe('Office Supplies');
});

test('ExpenseTableData handles array receipt_url without type error', function () {
    $dto = new ExpenseTableData(
        id: 402,
        expense_date: '2026-01-18',
        formatted_expense_date: '2026-01-18',
        amount: 1500.0,
        notes: 'Printer ink',
        attachment_receipt_url: ['url' => 'https://example.com/receipt.pdf', 'type' => 'application/pdf'],
    );

    expect($dto->attachment_receipt_url)->toBe(['url' => 'https://example.com/receipt.pdf', 'type' => 'application/pdf'])
        ->and($dto->notes)->toBe('Printer ink');
});

test('CustomerTableData maps customer attributes without sensitive fields', function () {
    $currency = new Currency(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $currency->id = 1;

    $customer = new Customer([
        'name' => 'Delta Inc',
        'contact_name' => 'David',
        'phone' => '123456789',
        'email' => 'david@delta.com',
        'company_id' => 1,
    ]);
    $customer->id = 501;
    $customer->due_amount = 7500;
    $customer->currency_id = 1;
    $customer->setRelation('currency', $currency);

    $dto = CustomerTableData::fromModel($customer);

    expect($dto->id)->toBe(501)
        ->and($dto->name)->toBe('Delta Inc')
        ->and($dto->email)->toBe('david@delta.com')
        ->and($dto->due_amount)->toBe(7500.0)
        ->and($dto->toArray())->not->toHaveKey('password')
        ->and($dto->toArray())->not->toHaveKey('remember_token');
});

test('UnitSummaryData maps model correctly', function () {
    $unit = new Unit(['name' => 'kg']);
    $unit->id = 7;

    $dto = UnitSummaryData::fromModel($unit);

    expect($dto)->not->toBeNull()
        ->and($dto->id)->toBe(7)
        ->and($dto->name)->toBe('kg');
});

test('ItemTableData maps item attributes and excludes heavy relations', function () {
    $unit = new Unit(['name' => 'box']);
    $unit->id = 3;

    $item = new Item([
        'name' => 'Widget A',
        'description' => 'A fine widget',
        'price' => 2500,
        'unit_id' => 3,
        'company_id' => 1,
        'created_at' => '2026-01-20',
    ]);
    $item->id = 101;
    $item->setRelation('unit', $unit);

    $dto = ItemTableData::fromModel($item);

    expect($dto->id)->toBe(101)
        ->and($dto->name)->toBe('Widget A')
        ->and($dto->price)->toBe(2500.0)
        ->and($dto->unit->name)->toBe('box')
        ->and($dto->toArray())->not->toHaveKey('taxes')
        ->and($dto->toArray())->not->toHaveKey('company');
});

test('RecurringInvoiceTableData maps minimal recurring invoice attributes', function () {
    $currency = new Currency(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $currency->id = 1;

    $customer = new Customer(['name' => 'Acme Corp', 'contact_name' => 'Alice']);
    $customer->id = 10;
    $customer->setRelation('currency', $currency);

    $recurring = new RecurringInvoice([
        'starts_at' => '2026-02-01',
        'status' => 'ACTIVE',
        'frequency' => 'MONTHLY',
        'total' => 15000,
        'company_id' => 1,
    ]);
    $recurring->id = 601;
    $recurring->customer_id = 10;
    $recurring->setRelation('customer', $customer);

    $dto = RecurringInvoiceTableData::fromModel($recurring);

    expect($dto->id)->toBe(601)
        ->and($dto->status)->toBe('ACTIVE')
        ->and($dto->frequency)->toBe('MONTHLY')
        ->and($dto->total)->toBe(15000.0)
        ->and($dto->customer->name)->toBe('Acme Corp')
        ->and($dto->toArray())->not->toHaveKey('items')
        ->and($dto->toArray())->not->toHaveKey('taxes')
        ->and($dto->toArray())->not->toHaveKey('invoices');
});
