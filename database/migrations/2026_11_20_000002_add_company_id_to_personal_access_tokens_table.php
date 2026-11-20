<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        if (! Schema::hasColumn('personal_access_tokens', 'company_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->unsignedInteger('company_id')->nullable()->after('tokenable_id');
                $table->index('company_id', 'personal_access_tokens_company_id_index');
                $table->foreign('company_id', 'personal_access_tokens_company_id_foreign')
                    ->references('id')
                    ->on('companies')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_access_tokens') || ! Schema::hasColumn('personal_access_tokens', 'company_id')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropForeign('personal_access_tokens_company_id_foreign');
            $table->dropIndex('personal_access_tokens_company_id_index');
            $table->dropColumn('company_id');
        });
    }
};
