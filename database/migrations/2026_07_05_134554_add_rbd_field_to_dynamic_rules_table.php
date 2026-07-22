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
        Schema::table('dynamic_rules', function (Blueprint $table) {
            // ── RBD (Booking Class) ───────────────────────────────────
            $table->json('including_rbd')->nullable()->after('markup_type');
            $table->json('excluding_rbd')->nullable()->after('including_rbd');
            // Or use string if it's a simple field
            // $table->string('rbd')->nullable()->after('markup_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_rules', function (Blueprint $table) {
            $table->dropColumn('including_rbd');
            $table->dropColumn('excluding_rbd');
        });
    }
};
