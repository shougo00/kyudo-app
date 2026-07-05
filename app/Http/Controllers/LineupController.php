<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\Record;

class LineupController extends Controller
{
    public function index(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId);

        $group = Group::with(['users' => function ($q) {
            $q->where('is_admin', false);
        }])->findOrFail($groupId);
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

        return view('lineup.index', compact('group', 'lineup', 'members', 'date', 'recordedUserIds'));
    }

    public function save(Request $request, $lineupId)
    {
        $lineup = Lineup::findOrFail($lineupId);

        $this->checkGroupAccess($lineup->group_id);

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

        $this->checkGroupAccess($lineup->group_id);

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

    private function checkGroupAccess($groupId): void
    {
        $user = auth()->user();

        if (!$user || !$user->groups()->where('groups.id', $groupId)->exists()) {
            abort(403, 'このグループにはアクセスできません');
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
    public function copyPrevious(Lineup $lineup)
    {
        $this->checkGroupAccess($lineup->group_id);

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
}
