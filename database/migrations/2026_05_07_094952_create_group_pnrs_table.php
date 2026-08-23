<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('group_pnrs', function (Blueprint $table) {
            $table->id();
            // $table->uuid('uuid')->unique()->index();

            // User relationship
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->index();

            // Group Details (Step 0)
            $table->enum('way_type', ['One Way', 'Round Way', 'Multi City'])
                ->nullable()
                ->index();
            $table->enum('group_type', ['Corporate', 'Leisure','Labour', 'Student', 'Umrah', 'Hajj', 'Other'])
                ->nullable()
                ->index();
            $table->string('airline', 100)->nullable()->index();
            $table->enum('class_type', ['Economy', 'Business', 'First'])
                ->nullable()
                ->index();
            $table->string('code_rbd', 50)->nullable();
            $table->date('receive_date')->nullable();
            $table->unsignedInteger('total_seat')->default(0);
            $table->string('pnr', 50)->nullable()->index();
            $table->timestamp('pnr_date')->nullable();
            $table->enum('gds', ['Sabre', 'Amadeus', 'Galileo'])
                ->nullable()
                ->index();
            $table->string('pcc', 50)->nullable();
            $table->text('remarks')->nullable();

            // Fare Details (Step 2)

            $table->enum('currency', ['USD', 'BDT'])->default('USD');
            $table->decimal('exchange_rate', 10, 2)->default(122.00);
            $table->decimal('base_fare', 15, 2)->nullable();
            $table->decimal('tax', 15, 2)->nullable();
            $table->decimal('estimate_fare_usd', 15, 2)->nullable();
            $table->decimal('estimate_fare_bdt', 15, 0)->nullable();

            // Payment Rules
            $table->text('payment_rules')->nullable();

            // Status and tracking
            $table->enum('status', ['active', 'inactive', 'expired', 'cancelled'])->default('active')->index();
            $table->decimal('total_paid_amount', 15, 2)->default(0.00);
            $table->decimal('remaining_amount', 15, 2)->default(0.00);
            $table->timestamp('last_payment_date')->nullable();
            $table->unsignedInteger('current_travelers_count')->default(0);

            // Tracking
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Soft deletes and timestamps
            $table->softDeletes();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index(['created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_pnrs');
    }
};
