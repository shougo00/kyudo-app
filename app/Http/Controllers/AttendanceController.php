<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;

class AttendanceController extends Controller
{
   public function index(Request $request, $groupId)
{
    $user = auth()->user();
    if (!$user->line_link_code && !$user->line_user_id) {
        do {
            $code = (string) random_int(100000, 999999);
        } while (\App\Models\User::where('line_link_code', $code)->exists());

        $user->update([
            'line_link_code' => $code,
        ]);

        $user->refresh();
    }

    // ★ここ追加（超重要）
    if (!$user->groups()->where('groups.id', $groupId)->exists()) {
        abort(403, 'このグループにはアクセスできません');
    }

    $group = Group::findOrFail($groupId);
    $date = $request->date ?? date('Y-m-d');

    $lineup = Lineup::firstOrCreate(
        [
            'group_id' => $groupId,
            'date' => $date,
        ],
        [
            'tate_size' => 3,
        ]
    );

    $member = LineupMember::firstOrCreate(
        [
            'lineup_id' => $lineup->id,
            'user_id' => $user->id,
        ],
        [
            'position' => null,
            'is_absent' => $user->all_absent,
            'is_late' => false,
        ]
    );

    return view('attendance.index', compact(
        'group',
        'user',
        'date',
        'lineup',
        'member'
    ));
}

    public function save(Request $request, $groupId)
    {
        $request->validate([
            'status' => 'required|in:present,late,absent',
            'date' => 'required|date',
        ]);

        $date = $request->date;
        $user = auth()->user();

        if (!$user->groups()->where('groups.id', $groupId)->exists()) {
            abort(403, 'このグループにはアクセスできません');
        }

        $lineup = Lineup::firstOrCreate(
            [
                'group_id' => $groupId,
                'date' => $date,
            ],
            [
                'tate_size' => 3,
            ]
        );

        $member = LineupMember::firstOrCreate(
            [
                'lineup_id' => $lineup->id,
                'user_id' => $user->id,
            ],
            [
                'position' => null,
                'is_absent' => false,
                'is_late' => false,
            ]
        );

        $member->update([
            'is_absent' => $request->status === 'absent',
            'is_late' => $request->status === 'late',
        ]);

        return response()->json(['ok' => true]);
    }
    
    public function allAbsent(Request $request, $groupId)
    {
        $request->validate([
            'all_absent' => 'required|boolean',
        ]);

        $user = auth()->user();

        if (!$user->groups()->where('groups.id', $groupId)->exists()) {
            abort(403, 'このグループにはアクセスできません');
        }

        $user->update([
            'all_absent' => $request->all_absent,
        ]);

        return response()->json(['ok' => true]);
    }
}