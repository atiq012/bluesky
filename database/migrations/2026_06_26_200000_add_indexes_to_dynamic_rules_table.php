<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dynamic_rules', function (Blueprint $table) {
            $table->index(['status', 'deleted_at'], 'dynamic_rules_status_deleted_idx');
            $table->index(['start_date', 'end_date'], 'dynamic_rules_date_range_idx');
            $table->index('api', 'dynamic_rules_api_idx');
            $table->index('agency_type', 'dynamic_rules_agency_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dynamic_rules', function (Blueprint $table) {
            $table->dropIndex('dynamic_rules_status_deleted_idx');
            $table->dropIndex('dynamic_rules_date_range_idx');
            $table->dropIndex('dynamic_rules_api_idx');
            $table->dropIndex('dynamic_rules_agency_type_idx');
        });
    }
};
