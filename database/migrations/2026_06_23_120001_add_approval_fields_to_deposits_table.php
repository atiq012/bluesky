<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->string('adjust_credit', 3)->nullable()->after('status');
            $table->text('approval_note')->nullable()->after('adjust_credit');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['adjust_credit', 'approval_note']);
        });
    }
};
