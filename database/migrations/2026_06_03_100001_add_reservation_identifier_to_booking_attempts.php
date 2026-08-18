<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->string('reservation_identifier', 150)->nullable()->after('pnr');
        });
    }

    public function down(): void
    {
        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->dropColumn('reservation_identifier');
        });
    }
};
