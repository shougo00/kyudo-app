<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'match_record_height_extra')) {
                $table->integer('match_record_height_extra')->default(60)->after('official_record_height_extra');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'match_record_height_extra')) {
                $table->dropColumn('match_record_height_extra');
            }
        });
    }
};
