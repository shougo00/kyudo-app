<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('match_teams', 'deleted_at')) {
            Schema::table('match_teams', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('match_team_members', 'date')) {
            Schema::table('match_team_members', function (Blueprint $table) {
                $table->date('date')->nullable()->after('match_team_id');
            });
        }

        DB::table('match_team_members')
            ->join('match_teams', 'match_team_members.match_team_id', '=', 'match_teams.id')
            ->select('match_team_members.id', 'match_teams.date')
            ->orderBy('match_team_members.id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('match_team_members')
                        ->where('id', $row->id)
                        ->update(['date' => $row->date]);
                }
            });

        try {
            Schema::table('match_team_members', function (Blueprint $table) {
                $table->index('match_team_id', 'match_team_members_team_id_index');
            });
        } catch (\Throwable $e) {
            // Index may already exist from a previous interrupted migration run.
        }

        try {
            Schema::table('match_team_members', function (Blueprint $table) {
                $table->dropUnique('match_team_members_match_team_id_tate_no_position_unique');
            });
        } catch (\Throwable $e) {
            // The old unique index may already be removed.
        }

        try {
            Schema::table('match_team_members', function (Blueprint $table) {
                $table->unique(['match_team_id', 'date', 'tate_no', 'position'], 'match_team_date_tate_position_unique');
            });
        } catch (\Throwable $e) {
            // The new unique index may already exist from a previous interrupted migration run.
        }
    }

    public function down(): void
    {
        Schema::table('match_team_members', function (Blueprint $table) {
            $table->dropUnique('match_team_date_tate_position_unique');
            $table->unique(['match_team_id', 'tate_no', 'position']);
            $table->dropColumn('date');
        });

        Schema::table('match_teams', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
