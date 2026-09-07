<?php

use App\Support\MatchTeamColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('match_teams') || ! Schema::hasColumn('match_teams', 'color')) {
            return;
        }

        $divisionIndexes = [];
        $teams = DB::table('match_teams')
            ->whereNull('deleted_at')
            ->orderBy('group_id')
            ->orderBy('division')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'group_id', 'division', 'color']);

        foreach ($teams as $team) {
            $division = in_array($team->division, ['male', 'female', 'mixed'], true)
                ? $team->division
                : 'mixed';
            $key = $team->group_id . '-' . $division;
            $index = $divisionIndexes[$key] ?? 0;
            $divisionIndexes[$key] = $index + 1;

            if (! MatchTeamColor::shouldRefreshLegacyDefault($team->color, $division)) {
                continue;
            }

            DB::table('match_teams')
                ->where('id', $team->id)
                ->update(['color' => MatchTeamColor::automaticColor($division, $index)]);
        }
    }

    public function down(): void
    {
        //
    }
};
