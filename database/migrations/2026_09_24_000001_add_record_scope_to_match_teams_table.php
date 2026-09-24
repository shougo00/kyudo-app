<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_teams', function (Blueprint $table) {
            if (! Schema::hasColumn('match_teams', 'record_scope')) {
                $table->string('record_scope', 20)->default('official')->after('group_id');
                $table->index(['group_id', 'record_scope'], 'match_teams_group_scope_index');
            }
        });

        DB::table('match_teams')->whereNull('record_scope')->update(['record_scope' => 'official']);
    }

    public function down(): void
    {
        Schema::table('match_teams', function (Blueprint $table) {
            if (Schema::hasColumn('match_teams', 'record_scope')) {
                $table->dropIndex('match_teams_group_scope_index');
                $table->dropColumn('record_scope');
            }
        });
    }
};
