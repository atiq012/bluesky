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
        Schema::create('travellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('title',10)->nullable();
            $table->string('first_name', 150);
            $table->string('last_name', 150);
            $table->string('full_name');
            $table->string('pax_type',8)->nullable()->comment('Adult,Child,Infant');
            $table->string('email', 50)->unique();
            $table->string('phone', 20);
            $table->string('nationality', 50)->default('Bangladeshi');
            $table->string('passport_number', 100)->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->date('dob')->nullable();
            $table->enum ('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('passport_path');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Add indexes for frequently searched/joined columns

            $table->index(['first_name', 'last_name']); // Composite index for name searches
            $table->index('passport_number');
            $table->index('pax_type');
            $table->index('phone');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travellers', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });
        Schema::dropIfExists('travellers');
    }
};
