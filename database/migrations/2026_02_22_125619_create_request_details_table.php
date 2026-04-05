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
        Schema::create('request_details', function (Blueprint $table) {
            $table->id();
            $table->integer('request_id');
            $table->text('from_user_id')->nullable();
            $table->text('to_user_id')->nullable();
            $table->boolean('send_notification')->default(0);
            $table->boolean('send_email')->default(0);
            $table->boolean('show_to_assignee')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_details');
    }
};
