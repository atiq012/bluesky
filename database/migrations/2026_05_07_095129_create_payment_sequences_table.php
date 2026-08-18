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
        Schema::create('payment_sequences', function (Blueprint $table) {
            $table->id();
            // $table->uuid('uuid')->unique()->index();

            $table->foreignId('group_pnr_id')
                ->onDelete('cascade')
                ->index();

            // Rest of your columns...
            $table->unsignedTinyInteger('sequence_order')->index();
            $table->string('term', 50);
            $table->decimal('percent', 5, 2)->nullable();
            $table->enum('unit', ['Percent', 'Fixed'])->default('Percent');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->timestamp('payment_date')->nullable();
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])
                ->default('pending')
                ->index();
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->timestamp('paid_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['group_pnr_id', 'status']);
            $table->index(['payment_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_sequences');
    }
};
