<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_teams', function (Blueprint $table) {
            if (! Schema::hasColumn('match_teams', 'color')) {
                $table->string('color', 7)->nullable()->after('division');
            }

            if (! Schema::hasColumn('match_teams', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('tate_size');
            }
        });

        Schema::table('match_tate_metas', function (Blueprint $table) {
            if (! Schema::hasColumn('match_tate_metas', 'tate_size')) {
                $table->integer('tate_size')->nullable()->after('tate_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('match_tate_metas', function (Blueprint $table) {
            if (Schema::hasColumn('match_tate_metas', 'tate_size')) {
                $table->dropColumn('tate_size');
            }
        });

        Schema::table('match_teams', function (Blueprint $table) {
            if (Schema::hasColumn('match_teams', 'sort_order')) {
                $table->dropColumn('sort_order');
            }

            if (Schema::hasColumn('match_teams', 'color')) {
                $table->dropColumn('color');
            }
        });
    }
};
