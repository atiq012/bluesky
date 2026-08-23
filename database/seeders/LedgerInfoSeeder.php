<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LedgerInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // command : php artisan db:seed --class=LedgerInfoSeeder

        // CSV data as an array
        $csvData = [
            ['ledger_name' => 'Debtors - BS', 'root_type' => 'Asset', 'type' => 'Accounts Receivable', 'code' => '3000'],
            ['ledger_name' => 'Creditors - BS', 'root_type' => 'Liability', 'type' => 'Accounts Payable', 'code' => '4000'],
            ['ledger_name' => 'AIT on Ticket Payable - BS', 'root_type' => 'Liability', 'type' => 'Tax Payable', 'code' => '5000'],
            ['ledger_name' => 'Markup/Other Charges Income - BS', 'root_type' => 'Income', 'type' => 'Sales', 'code' => '1000'],
            ['ledger_name' => 'Service Charge Income - BS', 'root_type' => 'Income', 'type' => 'Sales', 'code' => '1020'],
            ['ledger_name' => 'IATA Comm Income - BS', 'root_type' => 'Income', 'type' => 'Sales', 'code' => '1040'],
            ['ledger_name' => 'Extra Comm Income - BS', 'root_type' => 'Income', 'type' => 'Sales', 'code' => '1060'],
            ['ledger_name' => 'Standard Discount Expenses - BS', 'root_type' => 'Expense', 'type' => 'Discount', 'code' => '2000'],
            ['ledger_name' => 'Special Discount Expenses - BS', 'root_type' => 'Expense', 'type' => 'Discount', 'code' => '2030'],
            ['ledger_name' => 'Discount (BlueSky) Expenses - BS', 'root_type' => 'Expense', 'type' => 'Discount', 'code' => '2060'],
            ['ledger_name' => 'BRAC Bank 1009550770003 - BS', 'root_type' => 'Asset', 'type' => 'Bank Account', 'code' => '6010'],
            ['ledger_name' => 'BRAC Bank 1065998040001 - BS', 'root_type' => 'Asset', 'type' => 'Bank Account', 'code' => '6020'],
            ['ledger_name' => 'Bank Asia 00436001011 - BS', 'root_type' => 'Asset', 'type' => 'Bank Account', 'code' => '6030'],
            ['ledger_name' => 'Bkash 01325081966 - BS', 'root_type' => 'Asset', 'type' => 'Bank Account', 'code' => '6040'],
            ['ledger_name' => 'City Bank 3101157953001 - BS', 'root_type' => 'Asset', 'type' => 'Bank Account', 'code' => '6050'],
            ['ledger_name' => 'City Bank 3104220140001 - BS', 'root_type' => 'Asset', 'type' => 'Bank Account', 'code' => '6060'],
            ['ledger_name' => 'DBBL 2461200001403 - BS', 'root_type' => 'Asset', 'type' => 'Bank Account', 'code' => '6070'],
            ['ledger_name' => 'DBBL 2461200001492 - BS', 'root_type' => 'Asset', 'type' => 'Bank Account', 'code' => '6080'],
            ['ledger_name' => 'Islami Bank 20502760900010900 - BS', 'root_type' => 'Asset', 'type' => 'Bank Account', 'code' => '6090'],
            ['ledger_name' => 'Undeposited CGP Cash - BS', 'root_type' => 'Asset', 'type' => 'Cash In Hand', 'code' => '6100'],
            ['ledger_name' => 'Undeposited DAC Cash - BS', 'root_type' => 'Asset', 'type' => 'Cash In Hand', 'code' => '6110'],
            ['ledger_name' => 'Undeposited ZYL Cash - BS', 'root_type' => 'Asset', 'type' => 'Cash In Hand', 'code' => '6120'],
            ['ledger_name' => 'Cash Incentive Expenses - BS', 'root_type' => 'Expense', 'type' => 'Expense Account', 'code' => '6130'],
        ];

        // Insert data into the table
        foreach ($csvData as $data) {
            DB::table('ledger_info')->insert([
                'ledger_name' => $data['ledger_name'],
                'code' => $data['code'],
                'root_type' => $data['root_type'],
                'type' => $data['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Ledger info seeded successfully!');
    }

    /**
     * Reverse the seeds.
     */
    public function down(): void
    {
        DB::table('ledger_info')->truncate();
    }
}
