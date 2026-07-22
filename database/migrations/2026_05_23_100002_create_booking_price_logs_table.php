<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_price_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('booking_search_log_id')->nullable()->index();
            $table->unsignedBigInteger('booking_attempt_id')->nullable()->index();
            $table->uuid('request_id')->unique();
            $table->string('catalog_identifier', 100)->nullable();
            $table->string('offer_identifier', 150)->nullable();
            $table->string('outbound_offering_id', 30)->nullable();
            $table->string('outbound_product_ref', 30)->nullable();
            $table->string('inbound_offering_id', 30)->nullable();
            $table->string('inbound_product_ref', 30)->nullable();
            $table->json('selection_json')->nullable();
            $table->tinyInteger('way')->default(1);
            $table->char('from_airport', 3)->nullable();
            $table->char('to_airport', 3)->nullable();
            $table->date('dep_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->unsignedSmallInteger('adt')->default(1);
            $table->unsignedSmallInteger('cnn')->default(0);
            $table->unsignedSmallInteger('kid')->default(0);
            $table->unsignedSmallInteger('inf')->default(0);
            $table->string('cabin_class', 30)->default('Economy');
            $table->json('price_payload')->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->string('currency', 5)->default('BDT');
            $table->decimal('base_fare', 12, 2)->nullable();
            $table->decimal('total_taxes', 12, 2)->nullable();
            $table->string('provider', 50)->default('travelport_v2');
            $table->string('status', 20)->default('success');
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('response_file_path')->nullable();
            $table->unsignedBigInteger('response_size_bytes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('booking_search_log_id')
                ->references('id')
                ->on('booking_search_logs')
                ->nullOnDelete();

            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['from_airport', 'to_airport', 'dep_date']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_price_logs');
    }
};
