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
        //
        DB::unprepared('DROP FUNCTION IF EXISTS f_off_loc;
        CREATE FUNCTION f_off_loc (proid INT) RETURNS varchar(300) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
        BEGIN
        DECLARE dname varchar(300);
            select name into dname from officelocations where id=proid;
        RETURN dname;
        end;');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        DB::unprepared('DROP FUNCTION IF EXISTS f_off_loc;');


    }
};
