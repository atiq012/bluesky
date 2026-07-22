<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('price_offers', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('group_req_id')->unsigned();

            $table->string('request_type', 15);
            $table->string('group_type', 20);
            $table->string('class_type', 15);
            $table->string('class_code', 10);

            $table->string('currency', 10);
            $table->decimal('exchange_rate', 10, 2)->nullable();

            $table->string('offered_flight', 100)->nullable();
            $table->string('offered_flight_no', 100)->nullable();
            $table->string('offered_return_flight', 20)->nullable();

            $table->string('origin', 50)->nullable();
            $table->string('destination', 50)->nullable();
            $table->dateTime('departure_date')->nullable();
            $table->string('return_origin', 50)->nullable();
            $table->string('return_destination', 50)->nullable();
            $table->dateTime('return_date')->nullable();

            $table->integer('adult_traveler');
            $table->integer('child_traveler')->nullable();
            $table->integer('infant_traveler')->nullable();
            $table->integer('total_traveler');

            $table->decimal('adult_base_fare', 10, 2);
            $table->decimal('adult_tax', 10, 2);
            $table->decimal('adult_ait', 10, 2);
            $table->integer('adult_max_pax');

            $table->decimal('child_base_fare', 10, 2)->nullable();
            $table->decimal('child_tax', 10, 2)->nullable();
            $table->decimal('child_ait', 10, 2)->nullable();
            $table->integer('child_max_pax')->nullable();

            $table->decimal('infant_base_fare', 10, 2)->nullable();
            $table->decimal('infant_tax', 10, 2)->nullable();
            $table->decimal('infant_ait', 10, 2)->nullable();
            $table->integer('infant_max_pax')->nullable();

            $table->decimal('est_total_fare', 12, 2);
            $table->decimal('markup_value', 10, 2)->default(0);
            $table->string('markup_type', 10)->default('Percent');
            $table->decimal('service_charge_value', 10, 2)->default(0);
            $table->string('service_charge_type', 10)->default('Flat');
            $table->decimal('estimate_net_payable', 12, 2);

            $table->longText('policy_fare_rules');
            $table->string('pnr')->nullable();
            $table->string('pcc')->nullable();
            $table->string('gdc')->nullable();
            $table->string('pnr_date')->nullable();

            $table->string('status')->nullable();
            $table->longText('remarks')->nullable();

            $table->string('created_by');
            $table->string('updated_by');
            $table->timestamps();

            $table->foreign('group_req_id')->references('id')->on('group_requests')->onDelete('cascade');
            $table->index('group_req_id');
            $table->index('departure_date');
            $table->index('return_date');
            $table->index('destination');
        });
    }

    public function down()
    {
        Schema::dropIfExists('price_offers');
    }
};
