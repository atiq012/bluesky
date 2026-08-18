<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->string('erp_sync_status', 20)->nullable()->after('erp_journal_entry_id');
            $table->text('erp_sync_error')->nullable()->after('erp_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['erp_sync_status', 'erp_sync_error']);
        });
    }
};
