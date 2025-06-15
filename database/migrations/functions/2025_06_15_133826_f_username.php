<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS f_username;
        CREATE FUNCTION f_username (proid INT) RETURNS varchar(200) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
        BEGIN
        DECLARE pname varchar(200);
            select u.name  into pname from users u WHERE id=proid;
        RETURN pname;
        end;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS f_username;');

    }
};
