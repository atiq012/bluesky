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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('agent_code', 10)->unique()->nullable();
            $table->string('email', 50)->unique();
            $table->string('phone', 20);
            $table->string('logo_path', 255)->nullable();
            $table->string('country');
            $table->string('city');
            $table->string('zone');
            $table->string('address');
            $table->string('trade_licence', 50)->unique();
            $table->string('ca_number', 50)->nullable()->unique();
            $table->date('established_date')->nullable();
            $table->string('reg_number', 50)->nullable()->unique();
            $table->string('postal_code', 50)->nullable();
            $table->string('fax', 50)->nullable()->unique();
            $table->string('iata_number', 50)->nullable()->unique();
            $table->string('hajj_agency_number', 50)->nullable()->unique();
            $table->double('net_balance')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('kam')->nullable();
            $table->string('remarks')->nullable();
            $table->string('status')->default('Pending')->comment('Approved,Reject,Hold,Pending');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Add indexes for frequently searched/joined columns
            $table->index(['country', 'city', 'zone']); // Composite index for location searches
            $table->index('status');
            $table->index('user_id');
            $table->index('kam');

            // Add foreign key constraints
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('kam')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['kam']);
        });
        Schema::dropIfExists('agents');
    }
};
