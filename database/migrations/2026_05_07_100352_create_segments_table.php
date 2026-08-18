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
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            // $table->uuid('uuid')->unique()->index();

            // Relationship to group_pnr
            $table->foreignId('group_pnr_id')
                ->onDelete('cascade')
                ->index();

            // Segment identification
            $table->enum('segment_type', ['departure', 'return', 'multi_city'])
                ->default('departure')
                ->index();
            $table->unsignedTinyInteger('segment_order')
                ->default(1)
                ->index()
                ->comment('Order of segment in journey (1, 2, 3...)');

            // Flight Details
            $table->string('departure_from', 100)->nullable()->index()
                ->comment('Departure city/airport code');
            $table->timestamp('departure_datetime')->nullable()
                ->comment('Departure date and time');
            $table->string('arrival_to', 100)->nullable()
                ->comment('Arrival city/airport code');
            $table->timestamp('arrival_datetime')->nullable()
                ->comment('Arrival date and time');

            // Flight Information
            $table->string('flight_no', 50)->nullable()->index();

            // Baggage Details
            $table->string('cabin_baggage', 50)->nullable()
                ->comment('Cabin baggage allowance');
            $table->string('cabin_baggage_unit', 20)->nullable()
                ->default('Piece');
            $table->string('check_in_baggage', 50)->nullable()
                ->comment('Check-in baggage allowance');
            $table->string('check_in_baggage_unit', 20)->nullable()
                ->default('Kg');


            // Status
            $table->enum('status', ['active', 'cancelled', 'changed'])
                ->default('active')
                ->index();

            // Class and fare basis
            $table->enum('class_type', ['Economy', 'Business', 'First'])
                ->nullable()
                ->index();
            $table->string('fare_basis', 50)->nullable();
            $table->string('booking_class', 10)->nullable();

            // Additional info
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            // Tracking
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['departure_datetime']);
            $table->index(['flight_no', 'departure_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
