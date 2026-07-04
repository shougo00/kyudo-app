<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'official_tates_per_page')) {
                $table->integer('official_tates_per_page')->default(5)->after('invite_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'official_tates_per_page')) {
                $table->dropColumn('official_tates_per_page');
            }
        });
    }
};
