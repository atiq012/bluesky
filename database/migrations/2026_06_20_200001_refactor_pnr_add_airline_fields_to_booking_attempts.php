<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->string('gds_pnr', 30)->nullable()->after('pnr');
            $table->string('airline_pnr', 30)->nullable()->after('gds_pnr');
            $table->string('airline_code', 10)->nullable()->after('airline_pnr');
            $table->string('airline_name', 100)->nullable()->after('airline_code');
            $table->string('cabin_class', 30)->nullable()->after('airline_name');
            $table->dropColumn('pnr');
        });
    }

    public function down(): void
    {
        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->string('pnr', 20)->nullable()->after('booking_commit_session_id');
            $table->dropColumn(['gds_pnr', 'airline_pnr', 'airline_code', 'airline_name', 'cabin_class']);
        });
    }
};
