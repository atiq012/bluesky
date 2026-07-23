<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('offer_price_segments', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('offer_price_id')->unsigned();
            $table->unsignedTinyInteger('sequence');
            $table->string('origin', 50);
            $table->string('destination', 50);
            $table->dateTime('departure_date');
            $table->string('flight_no', 20)->nullable();
            $table->timestamps();

            $table->foreign('offer_price_id')->references('id')->on('price_offers')->onDelete('cascade');
            $table->index('offer_price_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('offer_price_segments');
    }
};
