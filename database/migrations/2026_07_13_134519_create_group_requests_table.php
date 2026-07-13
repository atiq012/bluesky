<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('group_requests', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('agent_code', 15)->nullable();
            $table->string('group_code', 30);
            $table->string('status')->default('New Request')->comment('New Request, On Process, Price Offered, Decline, Confirmed, Approved');

            // ── Trip Info ──
            $table->string('request_type', 15);       // oneway, roundway, multicity
            $table->string('group_type', 20);
            $table->string('class_type', 15)->nullable(); // Economy, Business, etc.
            $table->string('class_code', 10)->nullable();  // RBD: Y, B, M, etc.

            // ── One-way / Round-way fields (NULL for multicity) ──
            $table->dateTime('departure_date')->nullable();
            $table->dateTime('return_date')->nullable();
            $table->string('origin', 50)->nullable();
            $table->string('destination', 50)->nullable();
            $table->string('return_origin', 50)->nullable();
            $table->string('return_destination', 50)->nullable();
            $table->string('preferred_flight', 50)->nullable();
            $table->string('flight_no', 20)->nullable();
            $table->string('preferred_return_flight', 50)->nullable();

            // ── Passengers ──
            $table->integer('adult_traveler')->default(0);
            $table->integer('child_traveler')->default(0);
            $table->integer('infant_traveler')->default(0);
            $table->integer('total_traveler')->default(0);
            $table->integer('ticketing_passenger')->nullable();

            // ── Fare ──
            $table->decimal('per_person_fare', 12, 2)->nullable();
            $table->decimal('min_proposed_fare', 12, 2)->nullable();
            $table->decimal('max_proposed_fare', 12, 2)->nullable();
            $table->string('currency', 5)->default('BDT');

            // ── Requirements ──
            $table->string('special_requirements', 100)->nullable();
            $table->text('details_requirements')->nullable();
            $table->string('remarks', 300)->nullable();

            // ── Admin / Workflow ──
            $table->string('decline_note', 300)->nullable();
            $table->string('assigned_to')->nullable();
            $table->dateTime('assigned_date')->nullable();
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();

            // ── Indexes ──
            $table->index('agent_code');
            $table->index('status');
            $table->index('request_type');
            $table->index('departure_date');
            $table->index('destination');
        });
    }

    public function down()
    {
        Schema::dropIfExists('group_requests');
    }
};
