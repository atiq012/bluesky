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
        Schema::create('dynamic_rules', function (Blueprint $table) {
            $table->id();
            // ── Basic Information ─────────────────────────────────────
            $table->string('rule_name');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('run_continuously')->default(false);

            // ── Agency Information ────────────────────────────────────
            $table->string('agency_type')->nullable();
            $table->string('agency_group')->nullable();
            $table->json('including_agency')->nullable();
            $table->json('excluding_agency')->nullable();

            // ── API ───────────────────────────────────────────────────
            $table->string('api')->nullable();

            // ── Sector & Airline ──────────────────────────────────────
            $table->json('departure')->nullable();
            $table->json('arrival')->nullable();
            $table->json('including_airline')->nullable();
            $table->json('excluding_airline')->nullable();

            // ── Way Type & Class ──────────────────────────────────────
            $table->string('including_flight_type')->nullable();
            $table->string('excluding_flight_type')->nullable();
            $table->string('including_way_type')->nullable();
            $table->string('excluding_way_type')->nullable();
            $table->string('including_cabin_class')->nullable();
            $table->string('excluding_cabin_class')->nullable();

            // ── Commission ────────────────────────────────────────────
            $table->decimal('commission_value', 10, 2)->nullable();
            $table->enum('commission_type', ['percent', 'flat'])->default('percent');
            $table->boolean('extra_commission')->default(false)->nullable();
            $table->decimal('economy_extra', 10, 2)->nullable();
            $table->enum('economy_extra_type', ['percent', 'flat'])->default('percent');
            $table->decimal('business_extra', 10, 2)->nullable();
            $table->enum('business_extra_type', ['percent', 'flat'])->default('percent');

            // ── Discount ──────────────────────────────────────────────
            $table->decimal('stoppage_discount', 10, 2)->nullable();
            $table->enum('stoppage_discount_type', ['percent', 'flat'])->default('percent');

            // ── Service Charge ────────────────────────────────────────
            $table->decimal('service_charge', 10, 2)->nullable();
            $table->enum('service_charge_type', ['percent', 'flat'])->default('percent');

            // ── Markup ────────────────────────────────────────────────
            $table->decimal('markup_value', 10, 2)->nullable();
            $table->enum('markup_type', ['percent', 'flat'])->default('percent');

            // ── Create & Update Information ─────────────────────────────────
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();

            // status
            $table->boolean('status')->default(true);

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_rules');
    }
};
