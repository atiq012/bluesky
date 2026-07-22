<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
      public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('emp_id')->nullable()->change();
            $table->unsignedBigInteger('designation_id')->nullable()->change();
            $table->unsignedBigInteger('dept_id')->nullable()->change();
            $table->unsignedBigInteger('office_loc_id')->nullable()->change();
            $table->unsignedBigInteger('report_to')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('emp_id')->nullable(false)->change();
            $table->unsignedBigInteger('designation_id')->nullable(false)->change();
            $table->unsignedBigInteger('dept_id')->nullable(false)->change();
            $table->unsignedBigInteger('office_loc_id')->nullable(false)->change();
            $table->unsignedBigInteger('report_to')->nullable(false)->change();
        });
    }
};
