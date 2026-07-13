<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('group_request_segments', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->unsignedInteger('group_request_id');
            $table->integer('segment_order');          // 1, 2, 3...
            $table->string('origin', 50);
            $table->string('destination', 50);
            $table->dateTime('departure_date')->nullable();
            $table->timestamps();

            $table->foreign('group_request_id')
                  ->references('id')
                  ->on('group_requests')
                  ->cascadeOnDelete();

            $table->index('group_request_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('group_request_segments');
    }
};
