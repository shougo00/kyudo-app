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
use App\Support\MatchTeamColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->orderBy('sort_order')
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
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tate_size' => ['required', 'integer', 'min:1', 'max:15'],
        ]);

        $team = MatchTeam::create([
            'group_id' => $groupId,
            'date' => $validated['date'],
            'name' => $validated['name'],
            'division' => $validated['division'],
            'color' => $validated['color'] ?? MatchTeamColor::nextAutomaticColor((int) $groupId, $validated['division']),
            'tate_size' => $validated['tate_size'],
            'sort_order' => $this->nextSortOrder($groupId),
        ]);

        return redirect("/group/{$groupId}/match-records?date={$team->date}&team_id={$team->id}");
    }

    public function updateTeam(Request $request, MatchTeam $team)
    {
        $this->checkGroupAccess($team->group_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'division' => ['required', 'in:male,female,mixed'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tate_size' => ['required', 'integer', 'min:1', 'max:15'],
        ]);

        if ((int) $team->tate_size !== (int) $validated['tate_size']) {
            $this->syncTateSizesAfterTeamSizeChange($team, (int) $validated['tate_size']);
        }

        $validated['color'] = $validated['color']
            ?? ($team->division === $validated['division']
                ? MatchTeamColor::colorForStoredValue($team->color, $team->division)
                : MatchTeamColor::nextAutomaticColor((int) $team->group_id, $validated['division']));
        $team->update($validated);

        return back()->with('success', 'チーム設定を保存しました');
    }

    public function updateTeams(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId);

        $validated = $request->validate([
            'teams' => ['required', 'array', 'min:1'],
            'teams.*.id' => ['required', 'integer'],
            'teams.*.name' => ['required', 'string', 'max:255'],
            'teams.*.tate_size' => ['required', 'integer', 'min:1', 'max:15'],
            'teams.*.color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'teams.required' => '編集するチームがありません。',
            'teams.*.name.required' => 'チーム名を入力してください。',
            'teams.*.tate_size.required' => '人数を選択してください。',
            'teams.*.color.required' => 'チーム色を選択してください。',
        ]);

        $teamIds = collect($validated['teams'])->pluck('id')->map(fn($id) => (int) $id)->values();
        $activeTeams = MatchTeam::where('group_id', $groupId)
            ->whereNull('deleted_at')
            ->whereIn('id', $teamIds)
            ->get()
            ->keyBy('id');

        if ($activeTeams->count() !== $teamIds->unique()->count()) {
            abort(422, '編集できないチームが含まれています。');
        }

        DB::transaction(function () use ($validated, $activeTeams) {
            foreach (array_values($validated['teams']) as $index => $teamData) {
                $team = $activeTeams->get((int) $teamData['id']);

                if (!$team) {
                    continue;
                }

                if ((int) $team->tate_size !== (int) $teamData['tate_size']) {
                    $this->syncTateSizesAfterTeamSizeChange($team, (int) $teamData['tate_size']);
                }

                $team->update([
                    'name' => $teamData['name'],
                    'tate_size' => (int) $teamData['tate_size'],
                    'color' => $teamData['color'],
                    'sort_order' => $index + 1,
                ]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'チーム設定を保存しました');
    }

    public function saveTate(Request $request, MatchTeam $team)
    {
        $this->checkGroupAccess($team->group_id);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'tate_no' => ['required', 'integer', 'min:1'],
            'tate_size' => ['nullable', 'integer', 'min:1', 'max:15'],
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.position' => ['nullable', 'integer', 'min:1'],
            'members.*.absent' => ['boolean'],
            'members.*.late' => ['boolean'],
        ]);
        $tateSize = $this->tateSizeForSave($team, $validated['date'], (int) $validated['tate_no'], $validated['tate_size'] ?? null);

        MatchTeamMember::where('match_team_id', $team->id)
            ->where('date', $validated['date'])
            ->where('tate_no', $validated['tate_no'])
            ->delete();

        $group = Group::with(['users' => fn($q) => $q->where('is_admin', false)])->findOrFail($team->group_id);
        $attendanceByUserId = $this->attendanceMembersByUserId($group, $validated['date']);
        $recordUserIds = collect();

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

            if ($position && !$isAbsent) {
                $recordUserIds->push((int) $member['user_id']);
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

        $lineupSnapshotsByUserId = collect($validated['members'] ?? [])
            ->filter(fn($member) => !empty($member['position']) && empty($member['absent']))
            ->mapWithKeys(fn($member) => [
                (int) $member['user_id'] => [
                    'position' => (int) $member['position'],
                    'tate_size' => $tateSize,
                ],
            ]);

        MatchTateMeta::updateOrCreate(
            [
                'match_team_id' => $team->id,
                'date' => $validated['date'],
                'tate_no' => $validated['tate_no'],
            ],
            [
                'tate_size' => $tateSize,
            ]
        );

        $this->ensureRecordsWithShots($recordUserIds, $team, $validated['date'], (int) $validated['tate_no'], $lineupSnapshotsByUserId);

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

    private function nextSortOrder(int $groupId): int
    {
        return ((int) MatchTeam::withTrashed()
            ->where('group_id', $groupId)
            ->max('sort_order')) + 1;
    }

    private function syncTateSizesAfterTeamSizeChange(MatchTeam $team, int $newTateSize): void
    {
        $currentTateSize = max(1, (int) $team->tate_size);
        $newTateSize = max(1, min(15, $newTateSize));
        $memberTates = MatchTeamMember::where('match_team_id', $team->id)
            ->whereNotNull('date')
            ->whereNotNull('tate_no')
            ->get(['date', 'tate_no']);
        $recordTates = Record::where('match_team_id', $team->id)
            ->where('practice_type', 'match')
            ->whereNotNull('date')
            ->whereNotNull('tate_no')
            ->get(['date', 'tate_no']);
        $metaTates = MatchTateMeta::where('match_team_id', $team->id)
            ->whereNotNull('date')
            ->whereNotNull('tate_no')
            ->get(['date', 'tate_no']);

        $memberTates
            ->merge($recordTates)
            ->merge($metaTates)
            ->unique(fn($row) => $row->date . '-' . $row->tate_no)
            ->each(function ($row) use ($team, $currentTateSize, $newTateSize) {
                $date = (string) $row->date;
                $tateNo = (int) $row->tate_no;
                $meta = MatchTateMeta::firstOrNew([
                    'match_team_id' => $team->id,
                    'date' => $date,
                    'tate_no' => $tateNo,
                ]);

                if ($this->matchTateHasEnteredScore($team, $date, $tateNo)) {
                    if (!$meta->tate_size) {
                        $meta->tate_size = $currentTateSize;
                        $meta->save();
                    }

                    return;
                }

                $meta->tate_size = $newTateSize;
                $meta->save();

                MatchTeamMember::where('match_team_id', $team->id)
                    ->where('date', $date)
                    ->where('tate_no', $tateNo)
                    ->where('position', '>', $newTateSize)
                    ->delete();

                $activeUserIds = MatchTeamMember::where('match_team_id', $team->id)
                    ->where('date', $date)
                    ->where('tate_no', $tateNo)
                    ->whereNotNull('position')
                    ->where('position', '<=', $newTateSize)
                    ->pluck('user_id');

                $matchRecords = Record::where('match_team_id', $team->id)
                    ->where('date', $date)
                    ->where('practice_type', 'match')
                    ->where('tate_no', $tateNo)
                    ->get();
                $deleteRecordIds = $matchRecords
                    ->filter(fn($record) => ((int) ($record->lineup_position ?? 0) > $newTateSize)
                        || !$activeUserIds->contains((int) $record->user_id))
                    ->pluck('id');

                if ($deleteRecordIds->isNotEmpty()) {
                    Shot::whereIn('record_id', $deleteRecordIds)->delete();
                    Record::whereIn('id', $deleteRecordIds)->delete();
                }

                $updateRecordIds = $matchRecords
                    ->reject(fn($record) => $deleteRecordIds->contains($record->id))
                    ->pluck('id');

                if ($updateRecordIds->isNotEmpty()) {
                    Record::whereIn('id', $updateRecordIds)->update([
                        'lineup_tate_size' => $newTateSize,
                    ]);
                }
            });
    }

    private function matchTateHasEnteredScore(MatchTeam $team, string $date, int $tateNo): bool
    {
        $linkedOfficialRecordIds = MatchTeamMember::where('match_team_id', $team->id)
            ->where('date', $date)
            ->where('tate_no', $tateNo)
            ->whereNotNull('official_record_id')
            ->pluck('official_record_id');

        if ($linkedOfficialRecordIds->isNotEmpty() && $this->recordIdsHaveEnteredScore($linkedOfficialRecordIds)) {
            return true;
        }

        $matchRecordIds = Record::where('match_team_id', $team->id)
            ->where('date', $date)
            ->where('practice_type', 'match')
            ->where('tate_no', $tateNo)
            ->pluck('id');

        return $matchRecordIds->isNotEmpty() && $this->recordIdsHaveEnteredScore($matchRecordIds);
    }

    private function recordIdsHaveEnteredScore($recordIds): bool
    {
        $recordIds = collect($recordIds)->filter()->values();

        if ($recordIds->isEmpty()) {
            return false;
        }

        return Shot::whereIn('record_id', $recordIds)
            ->where(function ($query) {
                $query->whereNotNull('result')
                    ->orWhereNotNull('numeric_score');
            })
            ->exists();
    }

    private function tateSizeForSave(MatchTeam $team, string $date, int $tateNo, mixed $requestedTateSize): int
    {
        $savedTateSize = MatchTateMeta::where('match_team_id', $team->id)
            ->where('date', $date)
            ->where('tate_no', $tateNo)
            ->value('tate_size');

        return max(1, min(15, (int) ($savedTateSize ?: $requestedTateSize ?: $team->tate_size)));
    }

    private function ensureRecordsWithShots($userIds, MatchTeam $team, string $date, int $tateNo, $lineupSnapshotsByUserId = null): void
    {
        $userIds = collect($userIds)->filter()->unique()->values();
        $lineupSnapshotsByUserId = collect($lineupSnapshotsByUserId);

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
                $snapshot = $lineupSnapshotsByUserId->get((int) $userId, []);

                $recordInserts[] = [
                    'user_id' => $userId,
                    'date' => $date,
                    'tate_no' => $tateNo,
                    'practice_type' => 'match',
                    'match_team_id' => $team->id,
                    'official_sheet_no' => 1,
                    'lineup_position' => $snapshot['position'] ?? null,
                    'lineup_tate_size' => $snapshot['tate_size'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($recordInserts)) {
            Record::insert($recordInserts);
        }

        if ($lineupSnapshotsByUserId->isNotEmpty()) {
            foreach ($existingRecords as $record) {
                $snapshot = $lineupSnapshotsByUserId->get((int) $record->user_id);

                if ($snapshot && (is_null($record->lineup_position) || is_null($record->lineup_tate_size))) {
                    $record->update([
                        'lineup_position' => $record->lineup_position ?? $snapshot['position'],
                        'lineup_tate_size' => $record->lineup_tate_size ?? $snapshot['tate_size'],
                    ]);
                }
            }
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
