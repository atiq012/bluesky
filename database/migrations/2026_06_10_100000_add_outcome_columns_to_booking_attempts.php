<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->string('closing_stage', 40)->nullable()->after('status');
            $table->string('last_api_step', 50)->nullable()->after('closing_stage');
            $table->string('last_api_status', 20)->nullable()->after('last_api_step');
            $table->text('last_api_error')->nullable()->after('last_api_status');
            $table->timestamp('last_api_at')->nullable()->after('last_api_error');

            $table->index(['closing_stage', 'last_api_status']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->dropIndex(['closing_stage', 'last_api_status']);
            $table->dropColumn([
                'closing_stage',
                'last_api_step',
                'last_api_status',
                'last_api_error',
                'last_api_at',
            ]);
        });
    }
};
