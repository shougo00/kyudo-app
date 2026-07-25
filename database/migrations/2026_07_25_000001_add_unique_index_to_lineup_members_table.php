<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->deduplicateLineupMembers();

        Schema::table('lineup_members', function (Blueprint $table) {
            $table->unique(['lineup_id', 'user_id'], 'lineup_members_lineup_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lineup_members', function (Blueprint $table) {
            $table->dropUnique('lineup_members_lineup_user_unique');
        });
    }

    private function deduplicateLineupMembers(): void
    {
        $duplicates = DB::table('lineup_members')
            ->select('lineup_id', 'user_id', DB::raw('COUNT(*) as count'))
            ->groupBy('lineup_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('lineup_members')
                ->where('lineup_id', $duplicate->lineup_id)
                ->where('user_id', $duplicate->user_id)
                ->orderByRaw('position IS NULL')
                ->orderByDesc('updated_at')
                ->orderBy('id')
                ->get();

            $keep = $rows->first();

            if (!$keep) {
                continue;
            }

            $deleteIds = $rows
                ->pluck('id')
                ->reject(fn($id) => (int) $id === (int) $keep->id)
                ->values();

            if ($deleteIds->isNotEmpty()) {
                DB::table('lineup_members')
                    ->whereIn('id', $deleteIds)
                    ->delete();
            }
        }
    }
};
