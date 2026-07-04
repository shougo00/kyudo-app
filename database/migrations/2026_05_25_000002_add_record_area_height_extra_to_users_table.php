<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'official_record_height_extra')) {
                $table->integer('official_record_height_extra')->default(60)->after('all_absent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'official_record_height_extra')) {
                $table->dropColumn('official_record_height_extra');
            }
        });
    }
};
