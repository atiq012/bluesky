<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $idColumn = DB::select("SHOW COLUMNS FROM users WHERE Field = 'id'")[0] ?? null;
        if (!$idColumn || str_contains($idColumn->Type, 'bigint')) {
            return;
        }

        if (Schema::hasTable('password_histories')) {
            Schema::table('password_histories', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        if (Schema::hasTable('login_histories')) {
            Schema::table('login_histories', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        DB::statement('ALTER TABLE users MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        if (Schema::hasTable('password_histories')) {
            DB::statement('ALTER TABLE password_histories MODIFY user_id BIGINT UNSIGNED NOT NULL');

            Schema::table('password_histories', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('login_histories')) {
            DB::statement('ALTER TABLE login_histories MODIFY user_id BIGINT UNSIGNED NULL');

            Schema::table('login_histories', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // agents migration failed mid-run; drop orphan table so migrate can retry
        Schema::dropIfExists('agents');
    }

    public function down(): void
    {
        //
    }
};
