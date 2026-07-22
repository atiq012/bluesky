<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airline_restrictions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('scope', ['global', 'agency'])->default('global');
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->string('airline_code', 3);
            $table->tinyInteger('is_active')->default(1);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('agency_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['scope', 'is_active']);
            $table->index(['agency_id', 'is_active']);
            $table->index(['airline_code', 'agency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airline_restrictions');
    }
};
