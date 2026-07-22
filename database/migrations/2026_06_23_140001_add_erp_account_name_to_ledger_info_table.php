<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_info', function (Blueprint $table) {
            $table->string('erp_account_name')->nullable()->after('ledger_name');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_info', function (Blueprint $table) {
            $table->dropColumn('erp_account_name');
        });
    }
};
