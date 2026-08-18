<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('divisions')->exists()) {
            return;
        }

        // php artisan db:seed --class=DivisionSeeder

        $now = now();

        $rows = [
            ['id' => 1, 'name' => 'Chattagram', 'bn_name' => 'চট্টগ্রাম'],
            ['id' => 2, 'name' => 'Rajshahi', 'bn_name' => 'রাজশাহী'],
            ['id' => 3, 'name' => 'Khulna', 'bn_name' => 'খুলনা'],
            ['id' => 4, 'name' => 'Barisal', 'bn_name' => 'বরিশাল'],
            ['id' => 5, 'name' => 'Sylhet', 'bn_name' => 'সিলেট'],
            ['id' => 6, 'name' => 'Dhaka', 'bn_name' => 'ঢাকা'],
            ['id' => 7, 'name' => 'Rangpur', 'bn_name' => 'রংপুর'],
            ['id' => 8, 'name' => 'Mymensingh', 'bn_name' => 'ময়মনসিংহ'],
        ];

        foreach ($rows as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);

        DB::table('divisions')->insert($rows);
    }
}
