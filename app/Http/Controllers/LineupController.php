<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\Record;
use Illuminate\Support\Facades\DB;

class LineupController extends Controller
{
    private const MATCH_TEAM_COLORS = [
        'male' => '#0d6efd',
        'female' => '#dc3545',
        'mixed' => '#198754',
    ];

    public function index(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId, true);

        $group = Group::with(['users' => function ($q) {
            $q->where('is_admin', false);
        }])->findOrFail($groupId);
        $canEditLineup = $this->canEditGroupRecords($group);
        $date = $request->date ?? date('Y-m-d');
        $month = $request->month ?? \Carbon\Carbon::parse($date)->format('Y-m');
        $officialCompactEmptySlots = $request->boolean('compact_empty_slots', true);

        $lineup = Lineup::firstOrCreate(
            [
                'group_id' => $groupId,
                'date' => $date
            ],
            [
                'tate_size' => 9
            ]
        );

        $this->syncLineupMembers($lineup, $group);

        $activeUserIds = $group->users->pluck('id')->values();

        $members = $lineup->members()
        ->with('user')
        ->whereIn('user_id', $activeUserIds)
        ->whereHas('user', function ($q) {
            $q->where('is_admin', false);
        })
        ->orderByRaw('position IS NULL, position ASC')
        ->when($group->uses_grades, function ($query) {
            $query
                ->join('users as lineup_users', 'lineup_users.id', '=', 'lineup_members.user_id')
                ->orderByDesc('lineup_users.grade_level')
                ->orderBy('lineup_users.name')
                ->select('lineup_members.*');
        })
        ->get()
        ->unique('user_id')
        ->values();

        $officialSheetNos = Record::whereIn('user_id', $activeUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->pluck('official_sheet_no')
            ->filter()
            ->merge(DB::table('official_record_sheets')
                ->where('group_id', $groupId)
                ->where('date', $date)
                ->pluck('sheet_no'))
            ->push(1)
            ->map(fn($sheetNo) => (int) $sheetNo)
            ->unique()
            ->sort()
            ->values();

        $activeOfficialSheetNo = (int) ($request->sheet_no ?? $officialSheetNos->max() ?? 1);

        if ($activeOfficialSheetNo < 1) {
            $activeOfficialSheetNo = 1;
        }

        if (!$officialSheetNos->contains($activeOfficialSheetNo)) {
            $activeOfficialSheetNo = (int) ($officialSheetNos->max() ?? 1);
        }

        $recordedUserIds = Record::whereIn('user_id', $members->pluck('user_id'))
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', $activeOfficialSheetNo)
            ->whereHas('shots', function ($q) {
                $q->whereNotNull('result')
                    ->orWhereNotNull('numeric_score');
            })
            ->pluck('user_id')
            ->unique()
            ->values();

        $latestMatchUserColors = $this->latestMatchUserColors($group, $date);

        return view('lineup.index', compact('group', 'lineup', 'members', 'date', 'month', 'recordedUserIds', 'latestMatchUserColors', 'canEditLineup', 'activeOfficialSheetNo', 'officialCompactEmptySlots'));
    }

    public function save(Request $request, $lineupId)
    {
        $lineup = Lineup::findOrFail($lineupId);

        $this->checkGroupAccess($lineup->group_id, true, true);

        foreach ($request->members as $m) {
            LineupMember::where('id', $m['id'])
                ->where('lineup_id', $lineup->id)
                ->update([
                    'position' => $m['position'],
                    'is_absent' => $m['absent'],
                    'is_late' => $m['late'] ?? false,
                ]);
        }

        $lineup->update([
            'tate_size' => $request->tate_size
        ]);

        return response()->json(['ok' => true]);
    }

    public function random($lineupId)
    {
        $lineup = Lineup::findOrFail($lineupId);

        $this->checkGroupAccess($lineup->group_id, true, true);

        $members = LineupMember::where('lineup_id', $lineupId)
        ->whereIn('user_id', Group::findOrFail($lineup->group_id)->users()->where('users.is_admin', false)->pluck('users.id'))
        ->where('is_absent', false)
        ->whereHas('user', function ($q) {
            $q->where('is_admin', false);
        })
        ->get()
        ->unique('user_id')
        ->shuffle()
        ->values();

        foreach ($members as $i => $m) {
            $m->update([
                'position' => $i + 1
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function checkGroupAccess($groupId, bool $requiresGroupRecordPermission = false, bool $requiresGroupRecordEditPermission = false): void
    {
        $user = auth()->user();
        $group = Group::findOrFail($groupId);

        if (!$user || !$user->groups()->where('groups.id', $groupId)->exists()) {
            abort(403, 'このグループにはアクセスできません');
        }

        if ($this->isGroupHostOrAdmin($group, $user)) {
            return;
        }

        if (
            ($requiresGroupRecordPermission || $requiresGroupRecordEditPermission)
            && !$group->show_group_records_to_members
        ) {
            abort(403, 'ホスト以外は立順画面を表示できません');
        }

        if (
            $requiresGroupRecordEditPermission
            && !$group->allow_members_edit_group_records
        ) {
            abort(403, 'ホスト以外は立順を変更できません');
        }
    }

    private function syncLineupMembers(Lineup $lineup, Group $group): void
    {
        LineupMember::ensureForLineupUsers(
            $lineup,
            $group->users->where('is_admin', false)
        );
    }

    private function latestMatchUserColors(Group $group, string $date)
    {
        $teams = MatchTeam::withTrashed()
            ->with(['members' => fn($query) => $query->where('date', $date)])
            ->where('group_id', $group->id)
            ->whereHas('members', fn($query) => $query->where('date', $date))
            ->orderBy('id')
            ->get()
            ->values();

        return $teams
            ->flatMap(function ($team) {
                $latestTateNo = $team->members
                    ->pluck('tate_no')
                    ->filter()
                    ->max();

                if (!$latestTateNo) {
                    return collect();
                }

                $teamColor = self::MATCH_TEAM_COLORS[$team->division] ?? self::MATCH_TEAM_COLORS['mixed'];

                return $team->members
                    ->where('tate_no', $latestTateNo)
                    ->whereNotNull('position')
                    ->where('is_absent', false)
                    ->map(fn($member) => [
                        'user_id' => (int) $member->user_id,
                        'color' => $teamColor,
                    ]);
            })
            ->groupBy('user_id')
            ->map(fn($assignments) => $assignments->first()['color']);
    }

    public function copyPrevious(Lineup $lineup)
    {
        $this->checkGroupAccess($lineup->group_id, true, true);

        // 前回の「立順がセットされている日」を探す
        $previous = Lineup::where('group_id', $lineup->group_id)
            ->where('date', '<', $lineup->date)
            ->whereHas('members', function ($q) {
                $q->whereNotNull('position');
            })
            ->orderBy('date', 'desc')
            ->first();

        if (!$previous) {
            return back()->with('error', 'コピーできる前回の立順がありません');
        }

        // 何人立だけ前回に合わせる
        $lineup->update([
            'tate_size' => $previous->tate_size,
        ]);

        $previousMembers = $previous->members()->get()->unique('user_id');

        foreach ($previousMembers as $prevMember) {

            $currentMember = LineupMember::where('lineup_id', $lineup->id)
                ->where('user_id', $prevMember->user_id)
                ->first();

            if ($currentMember) {
                // 立順だけコピー
                // is_absent は変更しない
                $currentMember->update([
                    'position' => $prevMember->position,
                ]);
            }
        }

        return back()->with('success', '前回の立順をコピーしました');
    }

    private function isGroupHostOrAdmin(Group $group, $user): bool
    {
        return $user
            && (
                $user->username === 'KANRI'
                || (int) $group->host_user_id === (int) $user->id
            );
    }

    private function canEditGroupRecords(Group $group): bool
    {
        $user = auth()->user();

        return $this->isGroupHostOrAdmin($group, $user)
            || ((bool) $group->show_group_records_to_members && (bool) $group->allow_members_edit_group_records);
    }
}
