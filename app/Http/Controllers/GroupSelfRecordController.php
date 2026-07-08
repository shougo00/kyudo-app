<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupSelfRecordController extends Controller
{
    public function index(Request $request, Group $group): View
    {
        $this->authorizeHost($group);

        $date = $request->date ?? date('Y-m-d');
        $members = $this->members($group);
        $memberIds = $members->pluck('id')->values();
        $participantIds = $this->participantIds($request, $memberIds);

        if ($request->filled('user_id')) {
            $requestedUserId = (int) $request->query('user_id');

            if ($memberIds->contains($requestedUserId) && !$participantIds->contains($requestedUserId)) {
                $participantIds->push($requestedUserId);
            }
        }

        $participantIds = $participantIds->unique()->values();
        $participantQuery = $participantIds->implode(',');
        $activeMembers = $members->whereIn('id', $participantIds)->values();
        $selectedUser = $this->selectedUser($request, $members);

        $availableMembers = $members
            ->reject(fn(User $member) => $activeMembers->contains('id', $member->id))
            ->values();

        $records = collect();
        $totalShots = 0;
        $totalHits = 0;
        $hitRate = 0;

        if ($selectedUser) {
            $records = Record::with('shots')
                ->where('user_id', $selectedUser->id)
                ->where('date', $date)
                ->where('practice_type', 'self')
                ->orderBy('tate_no')
                ->get();

            $totalShots = $records->sum(fn($record) => $record->shots->whereNotNull('result')->count());
            $totalHits = $records->sum(fn($record) => $record->shots->where('result', 'hit')->count());
            $hitRate = $totalShots > 0 ? round(($totalHits / $totalShots) * 100, 3) : 0;
        }

        $prevDate = Carbon::parse($date)->subDay()->format('Y-m-d');
        $nextDate = Carbon::parse($date)->addDay()->format('Y-m-d');
        $numericScoreOptions = collect($group->numeric_score_options ?? [])
            ->filter(fn($option) => isset($option['value'], $option['color']))
            ->map(fn($option) => [
                'value' => (int) $option['value'],
                'color' => $option['color'],
            ])
            ->values();

        return view('group.self_records', compact(
            'group',
            'members',
            'activeMembers',
            'availableMembers',
            'selectedUser',
            'records',
            'date',
            'prevDate',
            'nextDate',
            'totalShots',
            'totalHits',
            'hitRate',
            'numericScoreOptions',
            'participantQuery'
        ));
    }

    public function store(Request $request, Group $group): RedirectResponse
    {
        $this->authorizeHost($group);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'user_id' => ['required', 'integer'],
            'participants' => ['nullable', 'string'],
        ]);

        $user = $this->memberOrFail($group, (int) $validated['user_id']);
        $participantIds = $this->participantIds($request, $this->members($group)->pluck('id')->values())
            ->push($user->id)
            ->unique()
            ->values();

        $maxTate = Record::where('user_id', $user->id)
            ->where('date', $validated['date'])
            ->where('practice_type', 'self')
            ->max('tate_no');

        $record = Record::create([
            'user_id' => $user->id,
            'date' => $validated['date'],
            'tate_no' => $maxTate ? $maxTate + 1 : 1,
            'practice_type' => 'self',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            Shot::create([
                'record_id' => $record->id,
                'shot_no' => $i,
                'result' => null,
            ]);
        }

        return redirect()
            ->route('group.self-records', [
                'group' => $group->id,
                'date' => $validated['date'],
                'user_id' => $user->id,
                'participants' => $participantIds->implode(','),
            ])
            ->withFragment("record-{$record->id}");
    }

    public function updateShot(Request $request, Group $group, Shot $shot): JsonResponse
    {
        $this->authorizeHost($group);
        $record = $this->selfRecordOrFail($group, $shot->record_id);

        $shot->result = $request->result;
        $shot->numeric_score = $request->has('numeric_score') ? $request->numeric_score : null;
        $shot->save();

        return response()->json(['success' => true]);
    }

    public function destroy(Group $group, Record $record): JsonResponse
    {
        $this->authorizeHost($group);
        $record = $this->selfRecordOrFail($group, $record->id);
        $date = $record->date;
        $userId = $record->user_id;

        $record->shots()->delete();
        $record->delete();

        Record::where('user_id', $userId)
            ->where('date', $date)
            ->where('practice_type', 'self')
            ->orderBy('tate_no')
            ->get()
            ->each(function (Record $remainingRecord, int $index) {
                $remainingRecord->update(['tate_no' => $index + 1]);
            });

        return response()->json(['success' => true]);
    }

    private function authorizeHost(Group $group): void
    {
        $user = auth()->user();

        if (!$user || ($user->username !== 'KANRI' && (int) $group->host_user_id !== (int) $user->id)) {
            abort(403, 'ホストだけがグループ自主練記録を操作できます');
        }
    }

    private function members(Group $group)
    {
        return $group->users()
            ->where('users.is_admin', false)
            ->when($group->uses_grades, fn($query) => $query->orderByDesc('users.grade_level'))
            ->orderBy('users.name')
            ->get();
    }

    private function selectedUser(Request $request, $members): ?User
    {
        if ($members->isEmpty() || !$request->filled('user_id')) {
            return null;
        }

        return $members->firstWhere('id', (int) $request->query('user_id'));
    }

    private function participantIds(Request $request, $memberIds)
    {
        return collect(explode(',', (string) $request->query('participants', $request->input('participants', ''))))
            ->map(fn($id) => (int) trim($id))
            ->filter(fn(int $id) => $id > 0 && $memberIds->contains($id))
            ->values();
    }

    private function memberOrFail(Group $group, int $userId): User
    {
        $user = $group->users()
            ->where('users.is_admin', false)
            ->where('users.id', $userId)
            ->first();

        if (!$user) {
            abort(404, 'メンバーが見つかりません');
        }

        return $user;
    }

    private function selfRecordOrFail(Group $group, int $recordId): Record
    {
        $memberIds = $group->users()
            ->where('users.is_admin', false)
            ->pluck('users.id');

        return Record::whereIn('user_id', $memberIds)
            ->where('practice_type', 'self')
            ->findOrFail($recordId);
    }
}
