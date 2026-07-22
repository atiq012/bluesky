<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_cache_versions')) {
            Schema::create('app_cache_versions', function (Blueprint $table) {
                $table->string('key', 64)->primary();
                $table->unsignedBigInteger('version')->default(1);
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        // Table may already exist from manual/partial deploy — seed only when missing.
        if (! DB::table('app_cache_versions')->where('key', 'dynamic_rules')->exists()) {
            DB::table('app_cache_versions')->insert([
                'key'     => 'dynamic_rules',
                'version' => 1,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cache_versions');
    }
};
