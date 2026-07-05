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

    // ★ここ追加（超重要）
    if (!$user->groups()->where('groups.id', $groupId)->exists()) {
        abort(403, 'このグループにはアクセスできません');
    }

    $group = Group::with(['users' => fn($query) => $query->where('users.is_admin', false)->orderBy('name')])->findOrFail($groupId);
    $date = $request->date ?? date('Y-m-d');
    $isHost = (int) $group->host_user_id === (int) $user->id;

    if (!$isHost && !$user->line_link_code && !$user->line_user_id) {
        do {
            $code = (string) random_int(100000, 999999);
        } while (\App\Models\User::where('line_link_code', $code)->exists());

        $user->update([
            'line_link_code' => $code,
        ]);

        $user->refresh();
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

    if ($isHost) {
        $attendanceMembers = $group->users->map(function ($memberUser) use ($lineup, $date) {
            $lineupMember = LineupMember::firstOrCreate(
                [
                    'lineup_id' => $lineup->id,
                    'user_id' => $memberUser->id,
                ],
                [
                    'position' => null,
                    'is_absent' => $memberUser->isDefaultAbsentForDate($date),
                    'is_late' => false,
                ]
            );

            return [
                'user' => $memberUser,
                'member' => $lineupMember,
            ];
        });

        return view('attendance.index', compact(
            'group',
            'user',
            'date',
            'lineup',
            'isHost',
            'attendanceMembers'
        ));
    }

    $member = LineupMember::firstOrCreate(
        [
            'lineup_id' => $lineup->id,
            'user_id' => $user->id,
        ],
        [
            'position' => null,
            'is_absent' => $user->isDefaultAbsentForDate($date),
            'is_late' => false,
        ]
    );

    return view('attendance.index', compact(
        'group',
        'user',
        'date',
        'lineup',
        'member',
        'isHost'
    ));
}

    public function save(Request $request, $groupId)
    {
        $request->validate([
            'status' => 'required|in:present,late,absent',
            'date' => 'required|date',
            'user_id' => 'nullable|integer',
        ]);

        $date = $request->date;
        $user = auth()->user();

        if (!$user->groups()->where('groups.id', $groupId)->exists()) {
            abort(403, 'このグループにはアクセスできません');
        }

        $group = Group::findOrFail($groupId);
        $targetUser = $user;

        if ($request->filled('user_id')) {
            if ((int) $group->host_user_id !== (int) $user->id) {
                abort(403, 'ホストだけが他メンバーの出席を変更できます');
            }

            $targetUser = $group->users()
                ->where('users.id', $request->integer('user_id'))
                ->where('users.is_admin', false)
                ->firstOrFail();
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
                'user_id' => $targetUser->id,
            ],
            [
                'position' => null,
                'is_absent' => $targetUser->isDefaultAbsentForDate($date),
                'is_late' => false,
            ]
        );

        $member->update([
            'is_absent' => $request->status === 'absent',
            'is_late' => $request->status === 'late',
        ]);

        return response()->json([
            'ok' => true,
            'user_id' => $targetUser->id,
        ]);
    }
    
    public function allAbsent(Request $request, $groupId)
    {
        $request->validate([
            'all_absent' => 'required|boolean',
            'date' => 'nullable|date',
        ]);

        $user = auth()->user();

        if (!$user->groups()->where('groups.id', $groupId)->exists()) {
            abort(403, 'このグループにはアクセスできません');
        }

        $user->update([
            'all_absent' => $request->all_absent,
            'attendance_weekdays' => null,
        ]);

        LineupMember::where('user_id', $user->id)
            ->whereHas('lineup', fn($query) => $query->where('group_id', $groupId))
            ->update([
                'is_absent' => $request->boolean('all_absent'),
                'is_late' => false,
            ]);

        $status = null;

        if ($request->filled('date')) {
            $lineup = Lineup::firstOrCreate(
                [
                    'group_id' => $groupId,
                    'date' => $request->date,
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
                    'is_absent' => $user->isDefaultAbsentForDate($request->date),
                    'is_late' => false,
                ]
            );

            $member->update([
                'is_absent' => $request->boolean('all_absent'),
                'is_late' => false,
            ]);

            $status = $member->is_absent ? 'absent' : 'present';
        }

        return response()->json([
            'ok' => true,
            'status' => $status,
        ]);
    }

    public function weeklySettings(Request $request, $groupId)
    {
        $validated = $request->validate([
            'attendance_weekdays' => ['nullable', 'array'],
            'attendance_weekdays.*' => ['integer', 'between:0,6'],
            'date' => ['nullable', 'date'],
        ]);

        $user = auth()->user();

        if (!$user->groups()->where('groups.id', $groupId)->exists()) {
            abort(403, 'このグループにはアクセスできません');
        }

        $weekdays = collect($validated['attendance_weekdays'] ?? [])
            ->map(fn($day) => (int) $day)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $hasWeekdaySetting = count($weekdays) > 0;

        $user->update([
            'attendance_weekdays' => $hasWeekdaySetting ? $weekdays : null,
            'all_absent' => $hasWeekdaySetting ? $user->all_absent : false,
        ]);

        if (!$hasWeekdaySetting) {
            LineupMember::where('user_id', $user->id)
                ->whereHas('lineup', fn($query) => $query->where('group_id', $groupId))
                ->update([
                    'is_absent' => false,
                    'is_late' => false,
                ]);
        }

        $date = $validated['date'] ?? null;
        $status = null;

        if ($date) {
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
                    'is_absent' => $user->isDefaultAbsentForDate($date),
                    'is_late' => false,
                ]
            );

            $member->update([
                'is_absent' => $user->isDefaultAbsentForDate($date),
                'is_late' => false,
            ]);

            $status = $member->is_absent ? 'absent' : 'present';
        }

        return response()->json([
            'ok' => true,
            'status' => $status,
            'all_absent' => (bool) $user->all_absent,
        ]);
    }
}
