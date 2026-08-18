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
        Schema::table('travellers', function (Blueprint $table) {
            // Add the agent_id column
            $table->unsignedBigInteger('agent_id')->after('passport_path')->nullable();

            // Add foreign key constraint if you have an agents table
            // $table->foreign('agent_id')->references('id')->on('agents')->onDelete('set null');

            // Add index for better performance
            $table->index('agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travellers', function (Blueprint $table) {
            // Drop foreign key first if you added it
            // $table->dropForeign(['agent_id']);

            // Drop the column and its index
            $table->dropIndex(['agent_id']);
            $table->dropColumn('agent_id');
        });
    }
};
