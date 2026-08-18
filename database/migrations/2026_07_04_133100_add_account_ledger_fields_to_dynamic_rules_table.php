<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dynamic_rules', function (Blueprint $table) {
            // ── Account Ledgers ───────────────────────────────────────
            $table->unsignedBigInteger('accountLedgerForCommission')->nullable()->after('markup_type');
            $table->unsignedBigInteger('accountLedgerForServiceCharge')->nullable()->after('accountLedgerForCommission');
            $table->unsignedBigInteger('accountLedgerForMarkup')->nullable()->after('accountLedgerForServiceCharge');
            $table->unsignedBigInteger('accountLedgerForStandardDiscount')->nullable()->after('accountLedgerForMarkup');
            $table->unsignedBigInteger('accountLedgerForSpecialDiscount')->nullable()->after('accountLedgerForStandardDiscount');
            $table->decimal('stoppageSpecialDiscount', 10, 2)->nullable();
            $table->enum('stoppageSpecialDiscountPercent', ['percent', 'flat'])->default('percent');

            // Add foreign key constraints if needed
            // Uncomment and modify the table name as per your database structure
            // $table->foreign('accountLedgerForCommission')->references('id')->on('chart_of_accounts')->onDelete('set null');
            // $table->foreign('accountLedgerForServiceCharge')->references('id')->on('chart_of_accounts')->onDelete('set null');
            // $table->foreign('accountLedgerForMarkup')->references('id')->on('chart_of_accounts')->onDelete('set null');
            // $table->foreign('accountLedgerForStandardDiscount')->references('id')->on('chart_of_accounts')->onDelete('set null');
            // $table->foreign('accountLedgerForSpecialDiscount')->references('id')->on('chart_of_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_rules', function (Blueprint $table) {
            // Drop columns
            $table->dropColumn([
                'accountLedgerForCommission',
                'accountLedgerForServiceCharge',
                'accountLedgerForMarkup',
                'accountLedgerForStandardDiscount',
                'accountLedgerForSpecialDiscount',
            ]);
        });
    }
};
