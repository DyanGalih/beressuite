<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('invoice_exports')) {
            Schema::create('invoice_exports', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('status', 32)->default('pending')->index();
                $table->string('file_name')->nullable();
                $table->string('file_path')->nullable();
                $table->string('disk', 32)->default('local');
                $table->unsignedBigInteger('file_disk_id')->nullable()->index();
                $table->json('filters')->nullable();
                $table->unsignedInteger('total_rows')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('file_disk_id')->references('id')->on('file_disks')->onDelete('set null');
            });

            return;
        }

        // Recover non-destructively if a prior MySQL run created the table before errno 150 aborted FK creation.
        Schema::table('invoice_exports', function (Blueprint $table) {
            $table->unsignedInteger('company_id')->change();
            $table->unsignedInteger('user_id')->nullable()->change();
        });

        $existingIndexes = array_column(Schema::getIndexes('invoice_exports'), 'name');
        $existingForeignKeys = array_column(Schema::getForeignKeys('invoice_exports'), 'name');

        Schema::table('invoice_exports', function (Blueprint $table) use ($existingIndexes, $existingForeignKeys) {
            if (! in_array('invoice_exports_company_id_index', $existingIndexes, true)) {
                $table->index('company_id');
            }
            if (! in_array('invoice_exports_user_id_index', $existingIndexes, true)) {
                $table->index('user_id');
            }
            if (! in_array('invoice_exports_status_index', $existingIndexes, true)) {
                $table->index('status');
            }
            if (! in_array('invoice_exports_file_disk_id_index', $existingIndexes, true)) {
                $table->index('file_disk_id');
            }
            if (! in_array('invoice_exports_company_id_foreign', $existingForeignKeys, true)) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            }
            if (! in_array('invoice_exports_user_id_foreign', $existingForeignKeys, true)) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }
            if (! in_array('invoice_exports_file_disk_id_foreign', $existingForeignKeys, true)) {
                $table->foreign('file_disk_id')->references('id')->on('file_disks')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_exports');
    }
};
