<?php

use Crater\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCompanyToFileDisksTable extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('file_disks', 'company_id')) {
            Schema::table('file_disks', function (Blueprint $table) {
                $table->unsignedInteger('company_id')->nullable()->after('id');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        $companyIds = Company::query()->pluck('id');

        if ($companyIds->count() === 1) {
            DB::table('file_disks')
                ->whereNull('company_id')
                ->update(['company_id' => $companyIds->first()]);
        }
    }

    public function down()
    {
        if (Schema::hasColumn('file_disks', 'company_id')) {
            Schema::table('file_disks', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
}
