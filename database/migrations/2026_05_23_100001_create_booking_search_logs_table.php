<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_search_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('booking_attempt_id')->nullable()->index();
            $table->uuid('request_id')->unique();

            $table->json('search_payload');
            $table->tinyInteger('way')->default(1);
            $table->char('from_airport', 3);
            $table->char('to_airport', 3);
            $table->date('dep_date');
            $table->date('arrival_date')->nullable();
            $table->unsignedSmallInteger('adt')->default(1);
            $table->unsignedSmallInteger('cnn')->default(0);
            $table->unsignedSmallInteger('kid')->default(0);
            $table->unsignedSmallInteger('inf')->default(0);
            $table->string('cabin_class', 30)->default('Economy');

            $table->string('provider', 50)->default('travelport_v2');
            $table->string('endpoint', 100)->default('v2/search');
            $table->string('response_file_path')->nullable();
            $table->unsignedBigInteger('response_size_bytes')->nullable();
            $table->unsignedInteger('flight_count')->default(0);
            $table->string('status', 20)->default('success');
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['from_airport', 'to_airport', 'dep_date']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_search_logs');
    }
};
