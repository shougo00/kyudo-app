<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->swapGroupOfficialShotNumbers();
    }

    public function down(): void
    {
        $this->swapGroupOfficialShotNumbers();
    }

    private function swapGroupOfficialShotNumbers(): void
    {
        $targetRecords = DB::table('records')
            ->where('practice_type', 'official')
            ->where(function ($query) {
                $query->whereNotNull('lineup_position')
                    ->orWhereExists(function ($exists) {
                        $exists->selectRaw('1')
                            ->from('lineups')
                            ->join('lineup_members', 'lineup_members.lineup_id', '=', 'lineups.id')
                            ->whereColumn('lineups.date', 'records.date')
                            ->whereColumn('lineup_members.user_id', 'records.user_id');
                    });
            })
            ->select('id')
            ->orderBy('id');

        $targetRecords->chunkById(500, function ($records) {
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
