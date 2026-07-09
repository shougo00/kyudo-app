<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'self_tates_per_day')) {
                $table->dropColumn('self_tates_per_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'self_tates_per_day')) {
                $table->integer('self_tates_per_day')->default(5)->after('official_tates_per_page');
            }
        });
    }
};
