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
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('type')->comment('cash,MFS,Check,bank transfer,credit request');
            $table->string('paid_account_no')->nullable();
            $table->decimal('amount')->nullable();
            $table->decimal('charge')->nullable();
            $table->decimal('total')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('issued_bank')->nullable();
            $table->date('reference_date')->nullable();
            $table->string('reference_file')->nullable();
            $table->string('remarks')->nullable();
            $table->string('status')->default('pending')->comment('pending,approved,rejected');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('paid_account_no');
            $table->index('reference_date');
            $table->index('type');
            $table->index('status');

            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
