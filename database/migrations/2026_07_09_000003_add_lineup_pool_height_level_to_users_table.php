<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'lineup_pool_height_level')) {
                $table->integer('lineup_pool_height_level')->default(5)->after('uses_camera');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'lineup_pool_height_level')) {
                $table->dropColumn('lineup_pool_height_level');
            }
        });
    }
};
