<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('groups')
            ->select('id', 'invite_code')
            ->orderBy('id')
            ->get();

        $usedCodes = $groups
            ->pluck('invite_code')
            ->filter(fn ($code) => is_string($code) && preg_match('/^\d{4}$/', $code))
            ->values()
            ->all();

        foreach ($groups as $group) {
            if (is_string($group->invite_code) && preg_match('/^\d{4}$/', $group->invite_code)) {
                continue;
            }

            do {
                $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            } while (in_array($code, $usedCodes, true));

            DB::table('groups')
                ->where('id', $group->id)
                ->update(['invite_code' => $code]);

            $usedCodes[] = $code;
        }
    }

    public function down(): void
    {
        //
    }
};
