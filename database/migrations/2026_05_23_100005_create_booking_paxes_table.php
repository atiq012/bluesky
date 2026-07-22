<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_paxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_attempt_id')->nullable()->index();
            $table->unsignedBigInteger('booking_session_id');
            $table->unsignedBigInteger('traveller_id')->nullable();
            $table->string('travelport_traveler_id', 150)->nullable();
            $table->string('pax_type', 8);
            $table->unsignedTinyInteger('sequence');
            $table->boolean('is_primary_contact')->default(false);
            $table->string('title', 10)->nullable();
            $table->string('first_name', 150);
            $table->string('middle_name', 150)->nullable();
            $table->string('last_name', 150);
            $table->date('dob')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('nationality', 80)->nullable();
            $table->string('passport_number', 100)->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('frequent_flyer_number', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('meal_preference', 50)->nullable();
            $table->boolean('wheelchair_needed')->nullable();
            $table->string('passport_image_path', 500)->nullable();
            $table->string('visa_image_path', 500)->nullable();
            $table->json('travelport_response')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('booking_attempt_id')
                ->references('id')
                ->on('booking_attempts')
                ->nullOnDelete();

            $table->foreign('booking_session_id')
                ->references('id')
                ->on('booking_sessions')
                ->cascadeOnDelete();

            $table->foreign('traveller_id')
                ->references('id')
                ->on('travellers')
                ->nullOnDelete();

            $table->index('pax_type');
            $table->index('sequence');
        });

        Schema::table('booking_search_logs', function (Blueprint $table) {
            $table->foreign('booking_attempt_id')
                ->references('id')
                ->on('booking_attempts')
                ->nullOnDelete();
        });

        Schema::table('booking_price_logs', function (Blueprint $table) {
            $table->foreign('booking_attempt_id')
                ->references('id')
                ->on('booking_attempts')
                ->nullOnDelete();
        });

        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->foreign('booking_workbench_session_id')
                ->references('id')
                ->on('booking_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_attempts', function (Blueprint $table) {
            $table->dropForeign(['booking_workbench_session_id']);
        });
        Schema::table('booking_price_logs', function (Blueprint $table) {
            $table->dropForeign(['booking_attempt_id']);
        });
        Schema::table('booking_search_logs', function (Blueprint $table) {
            $table->dropForeign(['booking_attempt_id']);
        });
        Schema::dropIfExists('booking_paxes');
    }
};
