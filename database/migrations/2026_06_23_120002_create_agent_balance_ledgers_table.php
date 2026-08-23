<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_balance_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('event_type', 50);
            $table->decimal('amount', 14, 2);
            $table->string('direction', 10);
            $table->decimal('net_balance_before', 14, 2)->default(0);
            $table->decimal('net_balance_after', 14, 2)->default(0);
            $table->decimal('credit_balance_before', 14, 2)->default(0);
            $table->decimal('credit_balance_after', 14, 2)->default(0);
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 255);
            $table->json('metadata')->nullable();
            $table->dateTime('transaction_at');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agent_id', 'transaction_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_balance_ledgers');
    }
};
