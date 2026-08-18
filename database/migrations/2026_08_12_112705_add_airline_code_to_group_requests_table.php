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
        Schema::table('group_requests', function (Blueprint $table) {
            $table->string('airline_code', 10)->nullable()->after('group_code');

            // Add index for better query performance
            $table->index('airline_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_requests', function (Blueprint $table) {
            $table->dropIndex(['airline_code']);
            $table->dropColumn('airline_code');
        });
    }
};
