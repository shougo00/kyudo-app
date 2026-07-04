<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'official_record_height_extra')) {
                $table->integer('official_record_height_extra')->default(60)->after('official_tates_per_page');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'official_record_height_extra')) {
                $table->dropColumn('official_record_height_extra');
            }
        });
    }
};
