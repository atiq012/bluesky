<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->bigInteger('charge_ledger_id')->nullable()->after('account_ledger_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropColumn('charge_ledger_id');
        });
    }
};
