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
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip',30)->nullable();
            $table->string('contry_code',10)->nullable();
            $table->string('city',10)->nullable();
            $table->string('login_attamp',10)->nullable();
            $table->string('device_type',10)->nullable();
            $table->string('os',10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
