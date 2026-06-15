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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('subcategory_id');
            $table->unsignedBigInteger('requester_id');
            $table->string('asset')->nullable();
            $table->string('request_type')->default('Request for solution')->comment('e.g.,Request for solution,Request for information, incident');
            $table->string('mode')->comment('e.g., email, phone, chat, web form')->default('web form');
            $table->string('level')->comment('e.g., tier 1, tier 2, tier 3, tier 4')->default('tier 1');
            $table->string('priority');
            $table->string('subject');
            $table->text('description');
            $table->string('file_path')->nullable();
            $table->string('status')->default('open')->comment('open, in progress, closed');
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
