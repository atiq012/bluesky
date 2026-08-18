<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 30)->default('in_progress')->index();
            $table->unsignedBigInteger('booking_search_log_id')->nullable()->index();
            $table->unsignedBigInteger('booking_price_log_id')->nullable()->index();
            $table->string('workbench_identifier', 150)->nullable()->index();
            $table->unsignedBigInteger('booking_workbench_session_id')->nullable()->index();
            $table->json('selection_json')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->unsignedBigInteger('booking_commit_session_id')->nullable();
            $table->string('pnr', 20)->nullable();
            $table->text('commit_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('booking_search_log_id')
                ->references('id')
                ->on('booking_search_logs')
                ->nullOnDelete();

            $table->foreign('booking_price_log_id')
                ->references('id')
                ->on('booking_price_logs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_attempts');
    }
};
