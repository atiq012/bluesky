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
        Schema::create('agent_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('attachment_type',20); //trade_licence_img,ca_certificate_img,tin_img,hajj_licence_img,nid_img,iata_certificate_img
            $table->string('attachment_path',300);
            $table->string('status')->default('Pending')->comment('Pending,Approved,Reject');
            $table->timestamps();

            // Foreign key constraints
            $table->index('attachment_type');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_images');
    }
};
