<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\MatchTeamMember;
use App\Models\MatchTateMeta;
use App\Models\Record;
use App\Models\Shot;
use Illuminate\Http\Request;

class MatchLineupController extends Controller
{
    public function index(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId);

        $group = Group::with(['users' => function ($q) {
            $q->where('is_admin', false);
        }])->findOrFail($groupId);

        $date = $request->date ?? date('Y-m-d');
        $matchAttendanceByUserId = $this->attendanceMembersByUserId($group, $date);

        $teams = MatchTeam::with(['members' => function ($q) use ($date) {
                $q->where('date', $date)->with('user');
            }])
            ->where('group_id', $groupId)
            ->orderBy('id')
            ->get();

        $selectedTeam = $request->team_id
            ? $teams->firstWhere('id', (int) $request->team_id)
            : $teams->first();

        $tateNo = max(1, (int) ($request->tate_no ?? 1));

        return view('match_lineup.index', compact('group', 'date', 'teams', 'selectedTeam', 'tateNo', 'matchAttendanceByUserId'));
    }

    public function storeTeam(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'division' => ['required', 'in:male,female,mixed'],
            'tate_size' => ['required', 'integer', 'min:1', 'max:15'],
        ]);

        $team = MatchTeam::create([
            'group_id' => $groupId,
            'date' => $validated['date'],
            'name' => $validated['name'],
            'division' => $validated['division'],
            'tate_size' => $validated['tate_size'],
        ]);

        return redirect("/group/{$groupId}/match-records?date={$team->date}&team_id={$team->id}");
    }

    public function updateTeam(Request $request, MatchTeam $team)
    {
        $this->checkGroupAccess($team->group_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'division' => ['required', 'in:male,female,mixed'],
            'tate_size' => ['required', 'integer', 'min:1', 'max:15'],
        ]);

        $team->update($validated);

        return back()->with('success', 'チーム設定を保存しました');
    }

    public function saveTate(Request $request, MatchTeam $team)
    {
        $this->checkGroupAccess($team->group_id);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'tate_no' => ['required', 'integer', 'min:1'],
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.position' => ['nullable', 'integer', 'min:1'],
            'members.*.absent' => ['boolean'],
            'members.*.late' => ['boolean'],
        ]);

        MatchTeamMember::where('match_team_id', $team->id)
            ->where('date', $validated['date'])
            ->where('tate_no', $validated['tate_no'])
            ->delete();

        $group = Group::with(['users' => fn($q) => $q->where('is_admin', false)])->findOrFail($team->group_id);
        $attendanceByUserId = $this->attendanceMembersByUserId($group, $validated['date']);

        foreach ($validated['members'] ?? [] as $member) {
            $isAbsent = (bool) ($member['absent'] ?? false);
            $isLate = !$isAbsent && (bool) ($member['late'] ?? false);
            $position = $member['position'] ?? null;

            $attendance = $attendanceByUserId->get((int) $member['user_id']);
            if ($attendance) {
                $attendance->update([
                    'is_absent' => $isAbsent,
                    'is_late' => $isLate,
                ]);
            }

            if (!$position && !$isAbsent && !$isLate) {
                continue;
            }

            MatchTeamMember::create([
                'match_team_id' => $team->id,
                'date' => $validated['date'],
                'user_id' => $member['user_id'],
                'tate_no' => $validated['tate_no'],
                'position' => $position,
                'is_absent' => $isAbsent,
                'is_late' => $isLate,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(MatchTeam $team)
    {
        $this->checkGroupAccess($team->group_id);

        $team->delete();

        return back()->with('success', 'チームを解散しました。記録は残ります。');
    }

    public function saveTateTimer(Request $request, MatchTeam $team)
    {
        $this->checkGroupAccess($team->group_id);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'tate_no' => ['required', 'integer', 'min:1'],
            'elapsed_seconds' => ['required', 'integer', 'min:0'],
            'is_running' => ['nullable', 'boolean'],
        ]);
        $isRunning = $request->boolean('is_running');

        MatchTateMeta::updateOrCreate(
            [
                'match_team_id' => $team->id,
                'date' => $validated['date'],
                'tate_no' => $validated['tate_no'],
            ],
            [
                'elapsed_seconds' => $validated['elapsed_seconds'],
                'is_timer_running' => $isRunning,
                'timer_started_at' => $isRunning ? now() : null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function saveTateScoringMode(Request $request, MatchTeam $team)
    {
        $this->checkGroupAccess($team->group_id);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'tate_no' => ['required', 'integer', 'min:1'],
            'scoring_mode' => ['required', 'in:hit_miss,numeric'],
        ]);

        $tateShots = Shot::whereHas('record', function ($query) use ($team, $validated) {
            $query->where('match_team_id', $team->id)
                ->where('date', $validated['date'])
                ->where('practice_type', 'match')
                ->where('tate_no', $validated['tate_no']);
        });

        if ($validated['scoring_mode'] === 'numeric' && (clone $tateShots)->whereNotNull('result')->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'この立に○×の記録が入っているため、数字モードに切り替えできません。',
            ], 409);
        }

        if ($validated['scoring_mode'] === 'hit_miss' && (clone $tateShots)->whereNotNull('numeric_score')->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'この立に数字の記録が入っているため、○×モードに戻せません。',
            ], 409);
        }

        MatchTateMeta::updateOrCreate(
            [
                'match_team_id' => $team->id,
                'date' => $validated['date'],
                'tate_no' => $validated['tate_no'],
            ],
            [
                'scoring_mode' => $validated['scoring_mode'],
            ]
        );

        return response()->json(['ok' => true]);
    }

    private function checkGroupAccess($groupId): void
    {
        $user = auth()->user();

        if (!$user || !$user->groups()->where('groups.id', $groupId)->exists()) {
            abort(403, 'このグループにはアクセスできません');
        }
    }

    private function attendanceMembersByUserId(Group $group, string $date)
    {
        $lineup = Lineup::firstOrCreate(
            [
                'group_id' => $group->id,
                'date' => $date,
            ],
            [
                'tate_size' => 9,
            ]
        );

        LineupMember::ensureForLineupUsers($lineup, $group->users);

        return $lineup->members()
            ->whereHas('user', fn($q) => $q->where('is_admin', false))
            ->get()
            ->keyBy('user_id');
    }

    private function ensureRecordsWithShots($userIds, MatchTeam $team, string $date, int $tateNo): void
    {
        $userIds = collect($userIds)->filter()->unique()->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $existingRecords = Record::whereIn('user_id', $userIds)
            ->where('date', $date)
            ->where('practice_type', 'match')
            ->where('match_team_id', $team->id)
            ->where('tate_no', $tateNo)
            ->get();

        $existingUserIds = $existingRecords->pluck('user_id')->toArray();
        $now = now();
        $recordInserts = [];

        foreach ($userIds as $userId) {
            if (!in_array($userId, $existingUserIds)) {
                $recordInserts[] = [
                    'user_id' => $userId,
                    'date' => $date,
                    'tate_no' => $tateNo,
                    'practice_type' => 'match',
                    'match_team_id' => $team->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($recordInserts)) {
            Record::insert($recordInserts);
        }

        $records = Record::whereIn('user_id', $userIds)
            ->where('date', $date)
            ->where('practice_type', 'match')
            ->where('match_team_id', $team->id)
            ->where('tate_no', $tateNo)
            ->get();

        $recordIds = $records->pluck('id');
        $existingShotKeys = Shot::whereIn('record_id', $recordIds)
            ->get()
            ->map(fn($shot) => $shot->record_id . '-' . $shot->shot_no)
            ->toArray();

        $shotInserts = [];

        foreach ($records as $record) {
            for ($i = 1; $i <= 4; $i++) {
                $key = $record->id . '-' . $i;

                if (!in_array($key, $existingShotKeys)) {
                    $shotInserts[] = [
                        'record_id' => $record->id,
                        'shot_no' => $i,
                        'result' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (!empty($shotInserts)) {
            Shot::insert($shotInserts);
        }
    }
}
