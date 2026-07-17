<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\Record;

class LineupController extends Controller
{
    public function index(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId, true);

        $group = Group::with(['users' => function ($q) {
            $q->where('is_admin', false);
        }])->findOrFail($groupId);
        $canEditLineup = $this->canEditGroupRecords($group);
        $date = $request->date ?? date('Y-m-d');

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
        ->get();

        $recordedUserIds = Record::whereIn('user_id', $members->pluck('user_id'))
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->whereHas('shots', function ($q) {
                $q->whereNotNull('result');
            })
            ->pluck('user_id')
            ->unique()
            ->values();

        $latestMatchUserIds = $this->latestMatchUserIds($group, $date);

        return view('lineup.index', compact('group', 'lineup', 'members', 'date', 'recordedUserIds', 'latestMatchUserIds', 'canEditLineup'));
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
        $members = $lineup->members()->get();
        $existingUserIds = $members->pluck('user_id')->toArray();

        foreach ($group->users as $user) {
            if (!in_array($user->id, $existingUserIds)) {
                LineupMember::create([
                    'lineup_id' => $lineup->id,
                    'user_id' => $user->id,
                    'position' => null,
                    'is_absent' => $user->isDefaultAbsentForDate($lineup->date),
                ]);
            }
        }
    }

    private function latestMatchUserIds(Group $group, string $date)
    {
        return MatchTeam::withTrashed()
            ->with(['members' => fn($query) => $query->where('date', $date)])
            ->where('group_id', $group->id)
            ->whereHas('members', fn($query) => $query->where('date', $date))
            ->get()
            ->flatMap(function ($team) {
                $latestTateNo = $team->members
                    ->pluck('tate_no')
                    ->filter()
                    ->max();

                if (!$latestTateNo) {
                    return collect();
                }

                return $team->members
                    ->where('tate_no', $latestTateNo)
                    ->whereNotNull('position')
                    ->where('is_absent', false)
                    ->where('is_late', false)
                    ->pluck('user_id');
            })
            ->unique()
            ->values();
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

        $previousMembers = $previous->members()->get();

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
