<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Record;
use App\Models\Shot;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\MatchTateMeta;
use Illuminate\Support\Facades\DB;

class GroupRecordController extends Controller
{
    public function index(Request $request, $groupId)
    {
        return $this->showRecords($request, $groupId, 'official');
    }

    public function matchIndex(Request $request, $groupId)
    {
        return $this->showRecords($request, $groupId, 'match');
    }

    private function showRecords(Request $request, $groupId, string $practiceType)
    {
        $this->checkGroupAccess($groupId);

        $group = Group::with('users')->findOrFail($groupId);
        $date = $request->date ?? date('Y-m-d');
        $maxTatesPerPage = max(1, (int) ($group->official_tates_per_page ?? 5));
        $recordHeightExtra = max(0, min(120, (int) (auth()->user()?->official_record_height_extra ?? 60)));
        $matchRecordHeightExtra = max(0, min(120, (int) (auth()->user()?->match_record_height_extra ?? 60)));

        if ($practiceType === 'match') {
            return $this->showMatchRecords($request, $group, $date);
        }

        $lineup = Lineup::with('members.user')
            ->where('group_id', $groupId)
            ->where('date', $date)
            ->first();

        $tateSize = 3;
        $lineupSlots = collect();
        $users = collect();
        $lineupSnapshotsByUserId = collect();
        $activeGroupUserIds = $group->users
            ->where('is_admin', false)
            ->pluck('id')
            ->values();

        if ($lineup) {
            $this->syncLineupMembers($lineup, $group);

            $lineup = Lineup::with('members.user')->findOrFail($lineup->id);
            $tateSize = $lineup->tate_size;

            $placedMembers = $lineup->members
                ->whereIn('user_id', $activeGroupUserIds)
                ->where('is_absent', false)
                ->filter(fn($m) => !is_null($m->position))
                ->sortBy('position')
                ->values();

            $users = $placedMembers->pluck('user')->filter()->values();
            $lineupSnapshotsByUserId = $placedMembers
                ->mapWithKeys(fn($member) => [
                    $member->user_id => [
                        'position' => $member->position,
                        'tate_size' => $tateSize,
                    ],
                ]);

            $maxPosition = $placedMembers->max('position') ?? 0;

            if ($maxPosition > 0) {
                $totalSlots = ceil($maxPosition / $tateSize) * $tateSize;

                for ($pos = 1; $pos <= $totalSlots; $pos++) {
                    $member = $placedMembers->firstWhere('position', $pos);

                    $lineupSlots->push((object)[
                        'position' => $pos,
                        'member' => $member,
                        'user' => $member?->user,
                        'is_empty' => is_null($member),
                    ]);
                }
            }
        }

        $groupUserIds = $activeGroupUserIds;
        $userIds = $users->pluck('id');

        $this->normalizeOfficialTateNos($date, $groupUserIds);

        $sheetNos = $this->officialSheetNos($groupId, $date, $groupUserIds);
        $activeSheetNo = (int) ($request->sheet_no ?? $sheetNos->max() ?? 1);

        if ($activeSheetNo < 1) {
            $activeSheetNo = 1;
        }

        if (!$sheetNos->contains($activeSheetNo)) {
            $activeSheetNo = (int) ($sheetNos->max() ?? 1);
        }

        $tates = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', $practiceType)
            ->where('official_sheet_no', $activeSheetNo)
            ->pluck('tate_no')
            ->unique()
            ->sort()
            ->values();

        $isCurrentSheet = $activeSheetNo === (int) ($sheetNos->max() ?? 1);
        $tateDisplayOffset = $this->officialTateDisplayOffset($groupUserIds, $date, $activeSheetNo, $tates);
        $activeSheetScoringMode = DB::table('official_record_sheets')
            ->where('group_id', $groupId)
            ->where('date', $date)
            ->where('sheet_no', $activeSheetNo)
            ->value('scoring_mode') ?? 'hit_miss';

        if ($isCurrentSheet && $userIds->isNotEmpty() && $tates->isNotEmpty()) {
            $this->ensureRecordsWithShots(
                $userIds,
                $date,
                $tates,
                $practiceType,
                null,
                $lineupSnapshotsByUserId,
                true,
                $activeSheetNo
            );

            $tates = Record::whereIn('user_id', $groupUserIds)
                ->where('date', $date)
                ->where('practice_type', $practiceType)
                ->where('official_sheet_no', $activeSheetNo)
                ->pluck('tate_no')
                ->unique()
                ->sort()
                ->values();
        }

        $recordRows = Record::with(['shots', 'user'])
            ->whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', $practiceType)
            ->where('official_sheet_no', $activeSheetNo)
            ->get();
        $hasEnteredOfficialShots = $recordRows
            ->contains(fn($record) => $record->shots
                ->contains(fn($shot) => !is_null($shot->result)));

        $records = $recordRows->groupBy('user_id');
        $users = $users
            ->merge($recordRows->pluck('user')->filter())
            ->filter()
            ->unique('id')
            ->values();

        $hitCounts = [];
        $numericCounts = [];

        foreach ($users as $user) {
            $hitCounts[$user->id] = 0;
            $numericCounts[$user->id] = 0;

            if (isset($records[$user->id])) {
                foreach ($records[$user->id] as $record) {
                    $hitCounts[$user->id] += $record->shots
                        ->where('result', 'hit')
                        ->count();
                    $numericCounts[$user->id] += $record->shots
                        ->sum(fn($shot) => (int) ($shot->numeric_score ?? 0));
                }
            }
        }

        $officialTateSlots = collect();
        $officialTateSizes = collect();

        foreach ($tates as $tateNo) {
            if ($isCurrentSheet && $lineupSlots->isNotEmpty()) {
                $officialTateSlots->put($tateNo, $lineupSlots);
                $officialTateSizes->put($tateNo, $tateSize);
                continue;
            }

            $tateRecords = $recordRows
                ->where('tate_no', $tateNo)
                ->filter(fn($record) => !is_null($record->lineup_position));

            if ($tateRecords->isEmpty()) {
                $officialTateSlots->put($tateNo, $lineupSlots);
                $officialTateSizes->put($tateNo, $tateSize);
                continue;
            }

            $snapshotTateSize = (int) ($tateRecords->pluck('lineup_tate_size')->filter()->first() ?? $tateSize);
            $maxPosition = max((int) $tateRecords->max('lineup_position'), $snapshotTateSize);
            $totalSlots = $snapshotTateSize > 0
                ? (int) ceil($maxPosition / $snapshotTateSize) * $snapshotTateSize
                : $maxPosition;
            $slots = collect();

            for ($pos = 1; $pos <= $totalSlots; $pos++) {
                $record = $tateRecords->firstWhere('lineup_position', $pos);

                $slots->push((object) [
                    'position' => $pos,
                    'member' => null,
                    'user' => $record?->user,
                    'is_empty' => is_null($record?->user),
                ]);
            }

            $officialTateSlots->put($tateNo, $slots);
            $officialTateSizes->put($tateNo, $snapshotTateSize);
        }

        $month = $request->month ?? \Carbon\Carbon::parse($date)->format('Y-m');

        $prevMonth = \Carbon\Carbon::parse($month . '-01')->subMonth()->format('Y-m');
        $nextMonth = \Carbon\Carbon::parse($month . '-01')->addMonth()->format('Y-m');

        $lineupDates = Lineup::where('group_id', $groupId)
            ->whereYear('date', \Carbon\Carbon::parse($month . '-01')->year)
            ->whereMonth('date', \Carbon\Carbon::parse($month . '-01')->month)
            ->whereHas('members', function ($q) {
                $q->where('is_absent', false)
                ->whereNotNull('position');
            })
            ->pluck('date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $recordLabel = $practiceType === 'match' ? '試合記録' : '正規連';
        $basePath = $practiceType === 'match'
            ? "/group/{$groupId}/match-records"
            : "/group/{$groupId}/records";
        $addTatePath = $practiceType === 'match'
            ? "/group/{$groupId}/match-add-tate"
            : "/group/{$groupId}/add-tate";
        $otherRecordPath = $practiceType === 'match'
            ? "/group/{$groupId}/records"
            : "/group/{$groupId}/match-records";
        $otherRecordLabel = $practiceType === 'match' ? '正規連用記録' : '試合用記録';
        $canSwitchOfficialSheet = $hasEnteredOfficialShots;

        return view('group.records', compact(
            'group',
            'records',
            'tates',
            'date',
            'users',
            'hitCounts',
            'tateSize',
            'month',
            'prevMonth',
            'nextMonth',
            'lineupDates',
            'lineupSlots',
            'practiceType',
            'recordLabel',
            'basePath',
            'addTatePath',
            'otherRecordPath',
            'otherRecordLabel',
            'officialTateSlots',
            'officialTateSizes',
            'activeSheetNo',
            'sheetNos',
            'isCurrentSheet',
            'tateDisplayOffset',
            'maxTatesPerPage',
            'recordHeightExtra',
            'matchRecordHeightExtra',
            'activeSheetScoringMode',
            'canSwitchOfficialSheet'
        ));
    }

    private function showMatchRecords(Request $request, Group $group, string $date)
    {
        $groupId = $group->id;
        $matchAttendanceByUserId = $this->attendanceMembersByUserId($group, $date);
        $teams = MatchTeam::withTrashed()
            ->with(['members' => function ($q) use ($date) {
                $q->where('date', $date)->with('user');
            }])
            ->where('group_id', $groupId)
            ->where(function ($q) use ($date) {
                $q->whereNull('deleted_at')
                    ->orWhereHas('records', function ($recordQuery) use ($date) {
                        $recordQuery->where('date', $date)
                            ->whereHas('shots', function ($shotQuery) {
                                $shotQuery->whereNotNull('result');
                            });
                    });
            })
            ->orderBy('id')
            ->get();

        $selectedTeam = $request->team_id
            ? $teams->firstWhere('id', (int) $request->team_id)
            : $teams->first();

        $selectedTateNo = max(1, (int) ($request->tate_no ?? 1));
        $tateSize = $selectedTeam?->tate_size ?? 3;
        $tates = collect();
        $matchTeamTates = collect();
        $matchTeamSlots = collect();
        $matchTeamUsers = collect();
        $users = collect();

        foreach ($teams as $team) {
            $teamTates = $team->members
                ->pluck('tate_no')
                ->unique()
                ->sort()
                ->values();

            if ($teamTates->isEmpty() && !$team->trashed()) {
                $teamTates = collect([1]);
            }

            $matchTeamTates->put($team->id, $teamTates);

            if ($team->members->isNotEmpty()) {
                $this->ensureMatchTeamRecords($team, $date, $teamTates, $matchAttendanceByUserId);

                foreach ($teamTates as $tateNo) {
                    $members = $team->members
                        ->where('tate_no', $tateNo)
                        ->filter(function ($member) use ($matchAttendanceByUserId) {
                            $attendance = $matchAttendanceByUserId->get($member->user_id);

                            return !is_null($member->position)
                                && !$attendance?->is_absent
                                && !$attendance?->is_late;
                        })
                        ->sortBy('position')
                        ->values();

                    $users = $users->merge($members->pluck('user')->filter());
                    $matchTeamUsers->put($team->id, $matchTeamUsers->get($team->id, collect())->merge($members->pluck('user')->filter()));
                    $slots = collect();

                    for ($pos = 1; $pos <= $team->tate_size; $pos++) {
                        $member = $members->firstWhere('position', $pos);

                        $slots->push((object) [
                            'position' => $pos,
                            'member' => $member,
                            'user' => $member?->user,
                            'is_empty' => is_null($member),
                        ]);
                    }

                    $teamSlots = $matchTeamSlots->get($team->id, collect());
                    $teamSlots->put($tateNo, $slots);
                    $matchTeamSlots->put($team->id, $teamSlots);
                }
            } elseif (!$team->trashed()) {
                $slots = collect();

                for ($pos = 1; $pos <= $team->tate_size; $pos++) {
                    $slots->push((object) [
                        'position' => $pos,
                        'member' => null,
                        'user' => null,
                        'is_empty' => true,
                    ]);
                }

                $matchTeamSlots->put($team->id, collect([1 => $slots]));
            }
        }

        if ($selectedTeam) {
            $tates = $matchTeamTates->get($selectedTeam->id, collect());
            $selectedTateNo = $request->filled('tate_no')
                ? $selectedTateNo
                : (int) ($tates->first() ?? 1);
        }

        $users = $users->unique('id')->values();
        $userIds = $users->pluck('id');

        $records = Record::with('shots')
            ->whereIn('user_id', $userIds)
            ->where('date', $date)
            ->where('practice_type', 'match')
            ->whereIn('match_team_id', $teams->pluck('id'))
            ->get()
            ->groupBy('match_team_id')
            ->map(fn($teamRecords) => $teamRecords->groupBy('user_id'));

        $hitCounts = [];
        $matchTateMetas = MatchTateMeta::whereIn('match_team_id', $teams->pluck('id'))
            ->where('date', $date)
            ->get()
            ->groupBy('match_team_id')
            ->map(fn($teamMetas) => $teamMetas->keyBy('tate_no'));

        foreach ($teams as $team) {
            $hitCounts[$team->id] = [];

            foreach (($matchTeamUsers->get($team->id, collect()))->unique('id')->values() as $user) {
                $hitCounts[$team->id][$user->id] = 0;

                if (isset($records[$team->id][$user->id])) {
                    foreach ($records[$team->id][$user->id] as $record) {
                        $hitCounts[$team->id][$user->id] += $record->shots
                            ->where('result', 'hit')
                            ->count();
                    }
                }
            }
        }

        $month = $request->month ?? \Carbon\Carbon::parse($date)->format('Y-m');
        $prevMonth = \Carbon\Carbon::parse($month . '-01')->subMonth()->format('Y-m');
        $nextMonth = \Carbon\Carbon::parse($month . '-01')->addMonth()->format('Y-m');

        $lineupDates = Record::where('practice_type', 'match')
            ->whereIn('match_team_id', MatchTeam::withTrashed()->where('group_id', $groupId)->pluck('id'))
            ->whereHas('shots', function ($q) {
                $q->whereNotNull('result');
            })
            ->whereYear('date', \Carbon\Carbon::parse($month . '-01')->year)
            ->whereMonth('date', \Carbon\Carbon::parse($month . '-01')->month)
            ->pluck('date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();

        $lineupSlots = collect();
        $matchTateSlots = $selectedTeam
            ? $matchTeamSlots->get($selectedTeam->id, collect())
            : collect();
        $practiceType = 'match';
        $recordLabel = '試合記録';
        $basePath = "/group/{$groupId}/match-records";
        $addTatePath = "/group/{$groupId}/match-add-tate";
        $otherRecordPath = "/group/{$groupId}/records";
        $otherRecordLabel = '正規連用記録';

        return view('group.records', compact(
            'group',
            'records',
            'tates',
            'date',
            'users',
            'hitCounts',
            'tateSize',
            'month',
            'prevMonth',
            'nextMonth',
            'lineupDates',
            'lineupSlots',
            'practiceType',
            'recordLabel',
            'basePath',
            'addTatePath',
            'otherRecordPath',
            'otherRecordLabel',
            'teams',
            'selectedTeam',
            'matchTateSlots',
            'selectedTateNo',
            'matchTeamTates',
            'matchTeamSlots',
            'matchTateMetas',
            'matchAttendanceByUserId'
        ));
    }

    public function addTate(Request $request, $groupId)
    {
        return $this->addTateForType($request, $groupId, 'official');
    }

    public function switchOfficialSheet(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId);

        $group = Group::with('users')->findOrFail($groupId);
        $date = $request->date ?? date('Y-m-d');
        $activeSheetNo = max(1, (int) ($request->sheet_no ?? 1));
        $activeGroupUserIds = $group->users
            ->where('is_admin', false)
            ->pluck('id')
            ->values();
        $hasEnteredOfficialShots = Record::whereIn('user_id', $activeGroupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', $activeSheetNo)
            ->whereHas('shots', function ($query) {
                $query->whereNotNull('result');
            })
            ->exists();

        if (!$hasEnteredOfficialShots) {
            return redirect("/group/{$groupId}/records?date={$date}&sheet_no={$activeSheetNo}");
        }

        $this->captureOfficialSheetLineup($group, $date, $activeSheetNo);

        $nextSheetNo = $activeSheetNo + 1;

        DB::table('official_record_sheets')->updateOrInsert(
            [
                'group_id' => $groupId,
                'date' => $date,
                'sheet_no' => $nextSheetNo,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return redirect("/group/{$groupId}/records?date={$date}&sheet_no={$nextSheetNo}");
    }

    public function addMatchTate(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId);

        $date = $request->date ?? date('Y-m-d');
        $team = MatchTeam::where('group_id', $groupId)
            ->when($request->team_id, fn($q) => $q->where('id', $request->team_id))
            ->first();

        if (!$team) {
            return redirect("/group/{$groupId}/match-lineup?date={$date}");
        }

        $maxTate = $team->members()->where('date', $date)->max('tate_no') ?? 0;
        $newTate = $maxTate + 1;

        if ($maxTate > 0) {
            $previousMeta = MatchTateMeta::where('match_team_id', $team->id)
                ->where('date', $date)
                ->where('tate_no', $maxTate)
                ->first();

            if ($previousMeta?->scoring_mode) {
                MatchTateMeta::updateOrCreate(
                    [
                        'match_team_id' => $team->id,
                        'date' => $date,
                        'tate_no' => $newTate,
                    ],
                    [
                        'scoring_mode' => $previousMeta->scoring_mode,
                    ]
                );
            }

            $now = now();
            $memberInserts = $team->members()
                ->where('date', $date)
                ->where('tate_no', $maxTate)
                ->get()
                ->map(fn($member) => [
                    'match_team_id' => $team->id,
                    'date' => $date,
                    'user_id' => $member->user_id,
                    'tate_no' => $newTate,
                    'position' => $member->position,
                    'is_absent' => $member->is_absent,
                    'is_late' => $member->is_late,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if (!empty($memberInserts)) {
                DB::table('match_team_members')->insert($memberInserts);
            }
        }

        return redirect("/group/{$groupId}/match-records?date={$date}&team_id={$team->id}&tate_no={$newTate}");
    }

    public function updateOfficialScoringMode(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'sheet_no' => ['required', 'integer', 'min:1'],
            'scoring_mode' => ['required', 'in:hit_miss,numeric'],
        ]);

        $groupUserIds = Group::findOrFail($groupId)
            ->users()
            ->where('is_admin', false)
            ->pluck('users.id');

        $sheetShots = Shot::whereHas('record', function ($query) use ($groupUserIds, $validated) {
            $query->whereIn('user_id', $groupUserIds)
                ->where('date', $validated['date'])
                ->where('practice_type', 'official')
                ->where('official_sheet_no', $validated['sheet_no']);
        });

        if ($validated['scoring_mode'] === 'numeric' && (clone $sheetShots)->whereNotNull('result')->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'このページに○×の記録が入っているため、数字モードに切り替えできません。',
            ], 409);
        }

        if ($validated['scoring_mode'] === 'hit_miss' && (clone $sheetShots)->whereNotNull('numeric_score')->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'このページに数字の記録が入っているため、○×モードに戻せません。',
            ], 409);
        }

        DB::table('official_record_sheets')->updateOrInsert(
            [
                'group_id' => $groupId,
                'date' => $validated['date'],
                'sheet_no' => $validated['sheet_no'],
            ],
            [
                'scoring_mode' => $validated['scoring_mode'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    private function addTateForType(Request $request, $groupId, string $practiceType)
    {
        $this->checkGroupAccess($groupId);

        $group = Group::with('users')->findOrFail($groupId);
        $date = $request->date ?? date('Y-m-d');
        $activeSheetNo = max(1, (int) ($request->sheet_no ?? 1));
        $maxTatesPerPage = max(1, (int) ($group->official_tates_per_page ?? 5));
        $redirectPath = $practiceType === 'match'
            ? "/group/{$groupId}/match-records"
            : "/group/{$groupId}/records";

        $lineup = Lineup::with('members.user')
            ->where('group_id', $groupId)
            ->where('date', $date)
            ->first();

        if (!$lineup) {
            return redirect("{$redirectPath}?date={$date}");
        }

        $this->syncLineupMembers($lineup, $group);

        $lineup = Lineup::with('members.user')->findOrFail($lineup->id);
        $activeGroupUserIds = $group->users
            ->where('is_admin', false)
            ->pluck('id')
            ->values();

        $placedMembers = $lineup->members
            ->whereIn('user_id', $activeGroupUserIds)
            ->where('is_absent', false)
            ->filter(fn($m) => !is_null($m->position))
            ->values();
        $users = $placedMembers
            ->sortBy('position')
            ->pluck('user')
            ->filter()
            ->values();

        if ($users->isEmpty()) {
            return redirect("{$redirectPath}?date={$date}");
        }

        $userIds = $users->pluck('id');
        $groupUserIds = $activeGroupUserIds;

        if ($practiceType === 'official') {
            $this->normalizeOfficialTateNos($date, $groupUserIds);
        }

        $currentSheetTates = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', $practiceType)
            ->when($practiceType === 'official', fn($query) => $query->where('official_sheet_no', $activeSheetNo))
            ->pluck('tate_no')
            ->unique()
            ->sort()
            ->values();

        if ($practiceType === 'official' && $currentSheetTates->count() >= $maxTatesPerPage) {
            return redirect("{$redirectPath}?date={$date}&sheet_no={$activeSheetNo}");
        }

        $lineupSnapshotsByUserId = $placedMembers
            ->mapWithKeys(fn($member) => [
                $member->user_id => [
                    'position' => $member->position,
                    'tate_size' => $lineup->tate_size,
                ],
            ]);

        if ($currentSheetTates->isNotEmpty()) {
            $this->ensureRecordsWithShots($userIds, $date, $currentSheetTates, $practiceType, null, $lineupSnapshotsByUserId, true, $activeSheetNo);
        }

        $maxTate = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', $practiceType)
            ->max('tate_no');

        $newTate = $maxTate ? $maxTate + 1 : 1;

        DB::table('official_record_sheets')->updateOrInsert(
            [
                'group_id' => $groupId,
                'date' => $date,
                'sheet_no' => $activeSheetNo,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->ensureRecordsWithShots($userIds, $date, collect([$newTate]), $practiceType, null, $lineupSnapshotsByUserId, false, $activeSheetNo);

        $sheetQuery = $practiceType === 'official' ? "&sheet_no={$activeSheetNo}" : '';

        return redirect("{$redirectPath}?date={$date}{$sheetQuery}");
    }

    public function updateShot(Request $request, $id)
    {
        $shot = Shot::with('record')->findOrFail($id);

        $record = $shot->record;

        $groupId = Lineup::where('date', $record->date)
            ->whereHas('members', function ($q) use ($record) {
                $q->where('user_id', $record->user_id);
            })
            ->value('group_id');

        if (!$groupId && $record->practice_type === 'match') {
            $groupId = MatchTeam::withTrashed()
                ->where('id', $record->match_team_id)
                ->orWhere(function ($query) use ($record) {
                    $query->where('date', $record->date)
                        ->whereHas('members', function ($q) use ($record) {
                            $q->where('user_id', $record->user_id)
                                ->where('tate_no', $record->tate_no);
                        });
                })
                ->value('group_id');
        }

        if ($groupId) {
            $this->checkGroupAccess($groupId);
        }

        $shot->result = $request->result ?: null;
        if ($request->has('numeric_score')) {
            $shot->numeric_score = $request->numeric_score;
        } else {
            $shot->numeric_score = null;
        }
        $shot->save();

        return response()->json(['success' => true]);
    }

    private function checkGroupAccess($groupId): void
    {
        $user = auth()->user();

        if (!$user || ($user->username !== 'KANRI' && !$user->groups()->where('groups.id', $groupId)->exists())) {
            abort(403, 'このグループにはアクセスできません');
        }
    }

    private function syncLineupMembers(Lineup $lineup, Group $group): void
    {
        $existingUserIds = $lineup->members->pluck('user_id')->toArray();

        foreach ($group->users as $user) {
            if (!in_array($user->id, $existingUserIds)) {
                LineupMember::create([
                    'lineup_id' => $lineup->id,
                    'user_id' => $user->id,
                    'position' => null,
                    'is_absent' => $user->isDefaultAbsentForDate($lineup->date),
                    'is_late' => false,
                ]);
            }
        }
    }

    private function officialSheetNos($groupId, string $date, $groupUserIds)
    {
        $recordSheetNos = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->pluck('official_sheet_no')
            ->filter()
            ->values();

        $savedSheetNos = DB::table('official_record_sheets')
            ->where('group_id', $groupId)
            ->where('date', $date)
            ->pluck('sheet_no');

        return $recordSheetNos
            ->merge($savedSheetNos)
            ->push(1)
            ->unique()
            ->sort()
            ->values();
    }

    private function officialTateDisplayOffset($groupUserIds, string $date, int $activeSheetNo, $tates): int
    {
        $tates = collect($tates);

        if ($activeSheetNo <= 1 || $tates->isEmpty()) {
            return 0;
        }

        $priorTateNos = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', '<', $activeSheetNo)
            ->pluck('tate_no')
            ->unique()
            ->values();

        if ($tates->intersect($priorTateNos)->isEmpty()) {
            return 0;
        }

        return Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', '<', $activeSheetNo)
            ->get(['official_sheet_no', 'tate_no'])
            ->unique(fn($record) => $record->official_sheet_no . '-' . $record->tate_no)
            ->count();
    }

    private function normalizeOfficialTateNos(string $date, $groupUserIds): void
    {
        $sheetTates = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->get(['official_sheet_no', 'tate_no'])
            ->filter(fn($record) => (int) $record->official_sheet_no > 0 && (int) $record->tate_no > 0)
            ->unique(fn($record) => $record->official_sheet_no . '-' . $record->tate_no)
            ->sortBy([
                ['official_sheet_no', 'asc'],
                ['tate_no', 'asc'],
            ])
            ->values();

        if ($sheetTates->isEmpty()) {
            return;
        }

        $hasCrossSheetDuplicate = $sheetTates
            ->groupBy('tate_no')
            ->contains(fn($records) => $records->pluck('official_sheet_no')->unique()->count() > 1);

        if (!$hasCrossSheetDuplicate) {
            return;
        }

        DB::transaction(function () use ($groupUserIds, $date, $sheetTates) {
            foreach ($sheetTates as $index => $sheetTate) {
                Record::whereIn('user_id', $groupUserIds)
                    ->where('date', $date)
                    ->where('practice_type', 'official')
                    ->where('official_sheet_no', $sheetTate->official_sheet_no)
                    ->where('tate_no', $sheetTate->tate_no)
                    ->update([
                        'tate_no' => -($index + 1),
                        'updated_at' => now(),
                    ]);
            }

            foreach ($sheetTates as $index => $sheetTate) {
                Record::whereIn('user_id', $groupUserIds)
                    ->where('date', $date)
                    ->where('practice_type', 'official')
                    ->where('official_sheet_no', $sheetTate->official_sheet_no)
                    ->where('tate_no', -($index + 1))
                    ->update([
                        'tate_no' => $index + 1,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    private function captureOfficialSheetLineup(Group $group, string $date, int $sheetNo): void
    {
        $lineup = Lineup::with('members.user')
            ->where('group_id', $group->id)
            ->where('date', $date)
            ->first();

        if (!$lineup) {
            return;
        }

        $this->syncLineupMembers($lineup, $group);
        $lineup = Lineup::with('members.user')->findOrFail($lineup->id);
        $activeGroupUserIds = $group->users
            ->where('is_admin', false)
            ->pluck('id')
            ->values();

        $placedMembers = $lineup->members
            ->whereIn('user_id', $activeGroupUserIds)
            ->where('is_absent', false)
            ->filter(fn($member) => !is_null($member->position))
            ->values();

        if ($placedMembers->isEmpty()) {
            return;
        }

        $snapshotsByUserId = $placedMembers->mapWithKeys(fn($member) => [
            $member->user_id => [
                'position' => $member->position,
                'tate_size' => $lineup->tate_size,
            ],
        ]);
        $userIds = $placedMembers->pluck('user_id')->values();
        $groupUserIds = $activeGroupUserIds;

        $tates = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', $sheetNo)
            ->pluck('tate_no')
            ->unique()
            ->values();

        if ($tates->isEmpty()) {
            return;
        }

        $this->ensureRecordsWithShots($userIds, $date, $tates, 'official', null, $snapshotsByUserId, true, $sheetNo);

        Record::whereIn('user_id', $groupUserIds)
            ->whereNotIn('user_id', $userIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', $sheetNo)
            ->whereIn('tate_no', $tates)
            ->update([
                'lineup_position' => null,
                'lineup_tate_size' => null,
            ]);
    }

    private function ensureRecordsWithShots($userIds, $date, $tateNos, string $practiceType = 'official', ?int $matchTeamId = null, $lineupSnapshotsByUserId = null, bool $overwriteSnapshot = false, int $officialSheetNo = 1): void
    {
        $userIds = collect($userIds)->values();
        $tateNos = collect($tateNos)->values();
        $lineupSnapshotsByUserId = collect($lineupSnapshotsByUserId);

        if ($userIds->isEmpty() || $tateNos->isEmpty()) {
            return;
        }

        // ===== 既存Recordをまとめて取得 =====
        $existingRecords = Record::whereIn('user_id', $userIds)
            ->where('date', $date)
            ->where('practice_type', $practiceType)
            ->when($practiceType === 'official', fn($q) => $q->where('official_sheet_no', $officialSheetNo))
            ->when($matchTeamId, fn($q) => $q->where('match_team_id', $matchTeamId))
            ->whereIn('tate_no', $tateNos)
            ->get();

        $existingKeys = $existingRecords
            ->map(fn($r) => $r->user_id . '-' . $r->tate_no)
            ->toArray();

        $recordInserts = [];
        $now = now();

        foreach ($userIds as $userId) {
            foreach ($tateNos as $tateNo) {
                $key = $userId . '-' . $tateNo;

                if (!in_array($key, $existingKeys)) {
                    $snapshot = $lineupSnapshotsByUserId->get($userId, []);

                    $recordInserts[] = [
                        'user_id' => $userId,
                        'date' => $date,
                        'tate_no' => $tateNo,
                        'practice_type' => $practiceType,
                        'official_sheet_no' => $practiceType === 'official' ? $officialSheetNo : 1,
                        'match_team_id' => $matchTeamId,
                        'lineup_position' => $snapshot['position'] ?? null,
                        'lineup_tate_size' => $snapshot['tate_size'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // ===== 足りないRecordをまとめて作成 =====
        if (!empty($recordInserts)) {
            Record::insert($recordInserts);
        }

        if ($lineupSnapshotsByUserId->isNotEmpty()) {
            foreach ($existingRecords as $record) {
                $snapshot = $lineupSnapshotsByUserId->get($record->user_id);

                if ($snapshot && ($overwriteSnapshot || is_null($record->lineup_position) || is_null($record->lineup_tate_size))) {
                    $record->update([
                        'lineup_position' => $overwriteSnapshot ? $snapshot['position'] : ($record->lineup_position ?? $snapshot['position']),
                        'lineup_tate_size' => $overwriteSnapshot ? $snapshot['tate_size'] : ($record->lineup_tate_size ?? $snapshot['tate_size']),
                    ]);
                }
            }
        }

        // ===== Recordを再取得 =====
        $records = Record::whereIn('user_id', $userIds)
            ->where('date', $date)
            ->where('practice_type', $practiceType)
            ->when($practiceType === 'official', fn($q) => $q->where('official_sheet_no', $officialSheetNo))
            ->when($matchTeamId, fn($q) => $q->where('match_team_id', $matchTeamId))
            ->whereIn('tate_no', $tateNos)
            ->get();

        $recordIds = $records->pluck('id');

        // ===== 既存Shotをまとめて取得 =====
        $existingShots = Shot::whereIn('record_id', $recordIds)
            ->get();

        $existingShotKeys = $existingShots
            ->map(fn($s) => $s->record_id . '-' . $s->shot_no)
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

        // ===== 足りないShotをまとめて作成 =====
        if (!empty($shotInserts)) {
            Shot::insert($shotInserts);
        }
    }

    private function ensureMatchTeamRecords(MatchTeam $team, string $date, $tateNos, $attendanceByUserId = null): void
    {
        $attendanceByUserId = collect($attendanceByUserId);
        $tateNos = collect($tateNos)->filter()->unique()->values();

        if ($tateNos->isEmpty()) {
            return;
        }

        $membersByTate = $team->members
            ->where('date', $date)
            ->whereIn('tate_no', $tateNos)
            ->filter(function ($member) use ($attendanceByUserId) {
                $attendance = $attendanceByUserId->get($member->user_id);

                return !is_null($member->position)
                    && !$attendance?->is_absent
                    && !$attendance?->is_late;
            })
            ->groupBy('tate_no')
            ->map(fn($members) => $members->pluck('user_id')->unique()->values());

        $userIds = $membersByTate
            ->flatten()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $existingRecords = Record::whereIn('user_id', $userIds)
            ->where('date', $date)
            ->where('practice_type', 'match')
            ->where('match_team_id', $team->id)
            ->whereIn('tate_no', $tateNos)
            ->get();

        $existingKeys = $existingRecords
            ->map(fn($record) => $record->user_id . '-' . $record->tate_no)
            ->all();

        $now = now();
        $recordInserts = [];

        foreach ($membersByTate as $tateNo => $tateUserIds) {
            foreach ($tateUserIds as $userId) {
                $key = $userId . '-' . $tateNo;

                if (in_array($key, $existingKeys, true)) {
                    continue;
                }

                $recordInserts[] = [
                    'user_id' => $userId,
                    'date' => $date,
                    'tate_no' => $tateNo,
                    'practice_type' => 'match',
                    'official_sheet_no' => 1,
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
            ->whereIn('tate_no', $tateNos)
            ->get();

        $recordIds = $records->pluck('id');

        if ($recordIds->isEmpty()) {
            return;
        }

        $existingShotKeys = Shot::whereIn('record_id', $recordIds)
            ->get(['record_id', 'shot_no'])
            ->map(fn($shot) => $shot->record_id . '-' . $shot->shot_no)
            ->all();

        $shotInserts = [];

        foreach ($records as $record) {
            for ($i = 1; $i <= 4; $i++) {
                $key = $record->id . '-' . $i;

                if (in_array($key, $existingShotKeys, true)) {
                    continue;
                }

                $shotInserts[] = [
                    'record_id' => $record->id,
                    'shot_no' => $i,
                    'result' => null,
                    'numeric_score' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($shotInserts)) {
            Shot::insert($shotInserts);
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

        $this->syncLineupMembers($lineup->load('members'), $group);

        return $lineup->members()
            ->whereHas('user', fn($q) => $q->where('is_admin', false))
            ->get()
            ->keyBy('user_id');
    }
}
