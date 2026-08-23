<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IssuedBankMFSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    // php artisan db:seed --class=IssuedBankMFSeeder
    public function run(): void
    {
        $banks = [
            ['name' => 'Sonali Bank PLC'],
            ['name' => 'Janata Bank PLC'],
            ['name' => 'Agrani Bank PLC'],
            ['name' => 'Rupali Bank PLC'],
            ['name' => 'BASIC Bank Limited'],
            ['name' => 'Bangladesh Development Bank PLC (BDBL)'],
            ['name' => 'AB Bank Limited'],
            ['name' => 'Agora Bank PLC'],
            ['name' => 'Al-Arafah Islami Bank PLC'],
            ['name' => 'Bangladesh Commerce Bank Limited'],
            ['name' => 'Bank Asia Limited'],
            ['name' => 'BRAC Bank PLC'],
            ['name' => 'City Bank Limited'],
            ['name' => 'Community Bank Bangladesh Limited'],
            ['name' => 'Dhaka Bank PLC'],
            ['name' => 'Dutch-Bangla Bank PLC'],
            ['name' => 'Eastern Bank PLC'],
            ['name' => 'Exim Bank PLC'],
            ['name' => 'First Security Islami Bank PLC'],
            ['name' => 'Global Islami Bank PLC'],
            ['name' => 'IFIC Bank PLC'],
            ['name' => 'Islami Bank Bangladesh PLC'],
            ['name' => 'Jamuna Bank PLC'],
            ['name' => 'Meghna Bank PLC'],
            ['name' => 'Mercantile Bank PLC'],
            ['name' => 'Midland Bank Limited'],
            ['name' => 'Modhumoti Bank PLC'],
            ['name' => 'Mutual Trust Bank PLC'],
            ['name' => 'National Bank Limited'],
            ['name' => 'National Credit & Commerce Bank PLC'],
            ['name' => 'NRB Bank Limited'],
            ['name' => 'NRB Commercial Bank Limited'],
            ['name' => 'NRB Global Bank Limited'],
            ['name' => 'One Bank PLC'],
            ['name' => 'Padma Bank PLC'],
            ['name' => 'Premier Bank PLC'],
            ['name' => 'Prime Bank PLC'],
            ['name' => 'Pubali Bank PLC'],
            ['name' => 'Shahjalal Islami Bank PLC'],
            ['name' => 'Shimanto Bank Limited'],
            ['name' => 'Social Islami Bank PLC'],
            ['name' => 'Southeast Bank PLC'],
            ['name' => 'Standard Bank PLC'],
            ['name' => 'The Farmers Bank Limited'],
            ['name' => 'Trust Bank Limited'],
            ['name' => 'Union Bank Limited'],
            ['name' => 'United Commercial Bank PLC'],
            ['name' => 'Uttara Bank PLC'],
            ['name' => 'Workers Bank Limited'],
            ['name' => 'Bangladesh Krishi Bank (BKB)'],
            ['name' => 'Rajshahi Krishi Unnayan Bank (RAKUB)'],
            ['name' => 'Bangladesh Samabay Bank Limited (BSBL)'],
            ['name' => 'Probashi Kallyan Bank'],
            ['name' => 'Ansar VDP Unnayan Bank'],
            ['name' => 'Karmasangsthan Bank'],
            ['name' => 'Palli Sanchay Bank'],
            ['name' => 'Citibank N.A.'],
            ['name' => 'HSBC Bangladesh'],
            ['name' => 'Standard Chartered Bank Bangladesh'],
            ['name' => 'The Commercial Bank of Ceylon PLC'],
            ['name' => 'Woori Bank Bangladesh'],
            ['name' => 'Bank Al-Falah Limited'],
            ['name' => 'Habib Bank Limited (HBL)'],
            ['name' => 'National Bank of Pakistan'],
            ['name' => 'State Bank of India (SBI)'],
        ];

        $bankData = [];
        foreach ($banks as $bank) {
            $bankData[] = [
                'name' => $bank['name'],
                'status' => 1, // Active by default
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('issued_bank_m_f_s')->insert($bankData);
    }
}
