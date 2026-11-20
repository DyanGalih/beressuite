<?php

use Illuminate\Support\Facades\Schema;

test('transaction composite performance indexes exist and migration is cleanly reversible', function () {
    $expected = [
        'invoices' => [
            'invoices_company_status_idx',
            'invoices_company_due_date_status_idx',
            'invoices_company_invoice_date_idx',
        ],
        'payments' => [
            'payments_company_payment_date_idx',
        ],
        'expenses' => [
            'expenses_company_expense_date_idx',
        ],
        'estimates' => [
            'estimates_company_status_idx',
        ],
    ];

    foreach ($expected as $table => $indexNames) {
        $existing = array_column(Schema::getIndexes($table), 'name');
        foreach ($indexNames as $indexName) {
            expect($existing)->toContain($indexName);
        }
    }

    $migration = require database_path(
        'migrations/2026_11_20_000001_add_performance_composite_indexes_to_transaction_tables.php'
    );

    $migration->down();

    foreach ($expected as $table => $indexNames) {
        $indexes = Schema::getIndexes($table);
        $existing = array_column($indexes, 'name');
        foreach ($indexNames as $indexName) {
            expect($existing)->not->toContain($indexName);
        }

        $hasCompanyLeadingIndex = collect($indexes)->contains(
            fn (array $index): bool => ($index['columns'][0] ?? null) === 'company_id'
        );
        expect($hasCompanyLeadingIndex)->toBeTrue();
    }

    $migration->up();

    foreach ($expected as $table => $indexNames) {
        $existing = array_column(Schema::getIndexes($table), 'name');
        foreach ($indexNames as $indexName) {
            expect($existing)->toContain($indexName);
        }
    }
});
