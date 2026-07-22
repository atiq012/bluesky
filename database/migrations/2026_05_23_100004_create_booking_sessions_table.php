<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('booking_attempt_id')->nullable()->index();
            $table->unsignedBigInteger('booking_price_log_id')->nullable()->index();
            $table->string('session_type', 50)->default('reservation_workbench');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('response_file_path')->nullable();
            $table->unsignedBigInteger('response_size_bytes')->nullable();
            $table->string('identifier_authority', 100)->nullable();
            $table->string('identifier_value', 150)->nullable();
            $table->string('provider', 50)->default('travelport_v2');
            $table->string('status', 20)->default('success');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('booking_attempt_id')
                ->references('id')
                ->on('booking_attempts')
                ->nullOnDelete();

            $table->foreign('booking_price_log_id')
                ->references('id')
                ->on('booking_price_logs')
                ->nullOnDelete();

            $table->index(['session_type', 'status']);
            $table->index('identifier_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_sessions');
    }
};
