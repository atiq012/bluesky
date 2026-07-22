<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_api_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('api_id');
            $table->tinyInteger('is_allowed')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('agency_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('api_id')->references('id')->on('api_management')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['agency_id', 'is_allowed']);
            $table->unique(['agency_id', 'api_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_api_permissions');
    }
};
