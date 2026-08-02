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
        Schema::create('group_p_a_x_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('title')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('passport_no');
            $table->date('expiry_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('nationality')->nullable();
            $table->string('pax_type')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Add indexes for frequently searched/joined columns
            $table->index(['first_name', 'last_name']); // Composite index for name searches
            $table->index('passport_no');
            $table->index('pax_type');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_p_a_x_infos');
    }
};
