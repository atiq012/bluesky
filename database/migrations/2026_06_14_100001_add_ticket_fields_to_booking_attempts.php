<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->json('ticket_numbers')->nullable()->after('reservation_identifier');
            $table->timestamp('ticketed_at')->nullable()->after('ticket_numbers');
        });
    }

    public function down(): void
    {
        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->dropColumn(['ticket_numbers', 'ticketed_at']);
        });
    }
};
