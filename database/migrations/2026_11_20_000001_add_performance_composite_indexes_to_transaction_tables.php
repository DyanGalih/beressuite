<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * @var array<string, array<string, list<string>>>
     */
    private const INDEXES = [
        'invoices' => [
            'invoices_company_status_idx' => ['company_id', 'status'],
            'invoices_company_due_date_status_idx' => ['company_id', 'due_date', 'status'],
            'invoices_company_invoice_date_idx' => ['company_id', 'invoice_date'],
        ],
        'payments' => [
            'payments_company_payment_date_idx' => ['company_id', 'payment_date'],
        ],
        'expenses' => [
            'expenses_company_expense_date_idx' => ['company_id', 'expense_date'],
        ],
        'estimates' => [
            'estimates_company_status_idx' => ['company_id', 'status'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $existing = array_column(Schema::getIndexes($tableName), 'name');

            Schema::table($tableName, function (Blueprint $table) use ($indexes, $existing): void {
                foreach ($indexes as $indexName => $columns) {
                    if (! in_array($indexName, $existing, true)) {
                        $table->index($columns, $indexName);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $existing = array_column(Schema::getIndexes($tableName), 'name');

            Schema::table($tableName, function (Blueprint $table) use ($indexes, $existing): void {
                foreach (array_keys($indexes) as $indexName) {
                    if (in_array($indexName, $existing, true)) {
                        $table->dropIndex($indexName);
                    }
                }
            });
        }
    }
};
