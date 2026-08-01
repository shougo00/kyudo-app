<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->swapGroupMatchShotNumbers();
    }

    public function down(): void
    {
        $this->swapGroupMatchShotNumbers();
    }

    private function swapGroupMatchShotNumbers(): void
    {
        DB::table('records')
            ->where('practice_type', 'match')
            ->whereNotNull('match_team_id')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($records) {
                $recordIds = $records->pluck('id');

                if ($recordIds->isEmpty()) {
                    return;
                }

                DB::table('shots')
                    ->whereIn('record_id', $recordIds)
                    ->whereIn('shot_no', [1, 2, 3, 4])
                    ->update([
                        'shot_no' => DB::raw('5 - shot_no'),
                    ]);
            });
    }
};
