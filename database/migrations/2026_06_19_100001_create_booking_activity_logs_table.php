<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_attempt_id');
            $table->string('action_type', 50);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 150)->nullable();
            $table->string('status_before', 50)->nullable();
            $table->string('status_after', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('booking_attempt_id')
                  ->references('id')
                  ->on('booking_attempts')
                  ->onDelete('cascade');

//will be

            $table->index(['booking_attempt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_activity_logs');
    }
};
