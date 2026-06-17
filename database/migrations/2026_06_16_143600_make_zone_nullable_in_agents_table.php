<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE agents MODIFY zone VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE agents SET zone = '' WHERE zone IS NULL");
        DB::statement('ALTER TABLE agents MODIFY zone VARCHAR(255) NOT NULL');
    }
};
