<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Polymorphic tables and columns that persist class-name discriminators.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private array $polymorphicColumns = [
        ['personal_access_tokens', 'tokenable_type'],
        ['assigned_roles', 'entity_type'],
        ['permissions', 'entity_type'],
        ['media', 'model_type'],
        ['custom_field_values', 'custom_field_valuable_type'],
        ['notifications', 'notifiable_type'],
        ['email_logs', 'mailable_type'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->polymorphicColumns as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $records = DB::table($table)->where($column, 'like', 'Crater\\%')->cursor();
                foreach ($records as $record) {
                    $id = $record->id;
                    $oldVal = (string) $record->{$column};
                    if (str_starts_with($oldVal, 'Crater\\')) {
                        $newVal = 'App\\'.mb_substr($oldVal, 7);
                        DB::table($table)->where('id', $id)->update([$column => $newVal]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->polymorphicColumns as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $records = DB::table($table)->where($column, 'like', 'App\\%')->cursor();
                foreach ($records as $record) {
                    $id = $record->id;
                    $oldVal = (string) $record->{$column};
                    if (str_starts_with($oldVal, 'App\\')) {
                        $newVal = 'Crater\\'.mb_substr($oldVal, 4);
                        DB::table($table)->where('id', $id)->update([$column => $newVal]);
                    }
                }
            }
        }
    }
};
