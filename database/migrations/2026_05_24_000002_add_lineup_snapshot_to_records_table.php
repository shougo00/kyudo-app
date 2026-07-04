<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            if (!Schema::hasColumn('records', 'lineup_position')) {
                $table->integer('lineup_position')->nullable()->after('match_team_id');
            }

            if (!Schema::hasColumn('records', 'lineup_tate_size')) {
                $table->integer('lineup_tate_size')->nullable()->after('lineup_position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            if (Schema::hasColumn('records', 'lineup_tate_size')) {
                $table->dropColumn('lineup_tate_size');
            }

            if (Schema::hasColumn('records', 'lineup_position')) {
                $table->dropColumn('lineup_position');
            }
        });
    }
};
