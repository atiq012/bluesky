<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('offer_price_payment_terms', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('offer_price_id')->unsigned();
            $table->string('sequence', 20);
            $table->decimal('value', 10, 2);
            $table->string('value_type', 10);
            $table->decimal('amount', 12, 2);
            $table->dateTime('due_date')->nullable();
            $table->timestamps();

            $table->foreign('offer_price_id')->references('id')->on('price_offers')->onDelete('cascade');
            $table->index('offer_price_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('offer_price_payment_terms');
    }
};
