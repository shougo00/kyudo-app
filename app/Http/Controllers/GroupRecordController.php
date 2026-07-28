<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Record;
use App\Models\Shot;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\MatchTeamMember;
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
        $this->checkGroupAccess($groupId, $practiceType === 'official');

        $group = Group::with('users')->findOrFail($groupId);
        $date = $request->date ?? date('Y-m-d');
        $maxTatesPerPage = max(1, (int) ($group->official_tates_per_page ?? 5));
        $recordHeightExtra = 60;
        $matchRecordHeightExtra = 60;
        $canEditGroupRecords = $practiceType === 'match' || $this->canEditGroupRecords($group);
        $officialCompactEmptySlots = $request->boolean('compact_empty_slots', true);
        $officialCompactEmptySlotsExplicit = $request->has('compact_empty_slots');
        $officialCompactEmptySlotsQuery = $officialCompactEmptySlotsExplicit
            ? '&compact_empty_slots=' . ($officialCompactEmptySlots ? '1' : '0')
            : '';
        $matchSelection = null;

        if ($practiceType === 'match') {
            return $this->showMatchRecords($request, $group, $date);
        }

        if ($request->filled(['match_team_id', 'match_tate_no', 'match_position'])) {
            $selectionTeam = MatchTeam::where('group_id', $groupId)
                ->whereNull('deleted_at')
                ->find($request->integer('match_team_id'));

            if ($selectionTeam) {
                $selectionTateNo = max(1, $request->integer('match_tate_no'));
                $selectionPosition = max(1, min($selectionTeam->tate_size, $request->integer('match_position')));
                $selectionReturnToOfficial = $request->input('return_to') === 'official';
                $selectionMonth = $request->month ?? \Carbon\Carbon::parse($date)->format('Y-m');
                $selectionSheetNo = max(1, (int) ($request->input('sheet_no') ?? 1));
                $assignedMembers = MatchTeamMember::with(['user', 'officialRecord'])
                    ->where('match_team_id', $selectionTeam->id)
                    ->where('date', $date)
                    ->where('tate_no', $selectionTateNo)
                    ->whereNotNull('position')
                    ->get()
                    ->keyBy('position');

                $matchSelection = [
                    'team' => $selectionTeam,
                    'team_id' => $selectionTeam->id,
                    'team_name' => $selectionTeam->name,
                    'tate_no' => $selectionTateNo,
                    'position' => $selectionPosition,
                    'tate_size' => $selectionTeam->tate_size,
                    'assigned_members' => $assignedMembers,
                    'return_to' => $selectionReturnToOfficial ? 'official' : 'match',
                    'back_url' => $selectionReturnToOfficial
                        ? "/group/{$groupId}/records?date={$date}&month={$selectionMonth}&sheet_no={$selectionSheetNo}{$officialCompactEmptySlotsQuery}#official-match-team-controls"
                        : "/group/{$groupId}/match-records?date={$date}&team_id={$selectionTeam->id}#match-team-{$selectionTeam->id}",
                    'back_label' => $selectionReturnToOfficial ? '正規連記録へ戻る' : '試合記録へ戻る',
                ];
            }
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
        $officialRecordUserIds = $activeGroupUserIds;

        if ($lineup) {
            $this->syncLineupMembers($lineup, $group);

            $lineup = Lineup::with('members.user')->findOrFail($lineup->id);
            $tateSize = $lineup->tate_size;
            $lineupUserIds = $lineup->members
                ->pluck('user_id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values();
            $officialRecordUserIds = $officialRecordUserIds
                ->merge($this->enteredOfficialRecordUserIds($lineupUserIds, $date))
                ->unique()
                ->values();

            $placedMembers = $lineup->members
                ->whereIn('user_id', $activeGroupUserIds)
                ->where('is_absent', false)
                ->filter(fn($m) => !is_null($m->position))
                ->sortBy('position')
                ->unique('user_id')
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

        $groupUserIds = $officialRecordUserIds;
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

        $isCurrentSheet = $activeSheetNo === (int) ($sheetNos->max() ?? 1);

        if ($isCurrentSheet && $userIds->isEmpty()) {
            $this->deleteEmptyOfficialSheetRecords($groupUserIds, $date, $activeSheetNo);
        }

        if ($isCurrentSheet) {
            $this->createInitialOfficialSheetRecordsIfNeeded(
                $group,
                $date,
                $activeSheetNo,
                $userIds,
                $groupUserIds,
                $maxTatesPerPage,
                $lineupSnapshotsByUserId
            );
        }

        $tates = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', $practiceType)
            ->where('official_sheet_no', $activeSheetNo)
            ->pluck('tate_no')
            ->unique()
            ->sort()
            ->values();

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
                ->contains(fn($shot) => !is_null($shot->result) || !is_null($shot->numeric_score)));

        $records = $recordRows->groupBy('user_id');
        if ($lineupSlots->isNotEmpty()) {
            $users = $users
                ->merge($recordRows->pluck('user')->filter())
                ->filter()
                ->unique('id')
                ->values();
        }

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
        $displayLineupSlots = $this->officialDisplaySlots($lineupSlots, $officialCompactEmptySlots, $tateSize);
        $displayLineupTateSize = $this->officialDisplayTateSize($lineupSlots, $officialCompactEmptySlots, $tateSize);

        foreach ($tates as $tateNo) {
            $tateRecords = $recordRows
                ->where('tate_no', $tateNo)
                ->filter(fn($record) => !is_null($record->lineup_position));

            if ($isCurrentSheet && $lineupSlots->isNotEmpty()) {
                $hasEnteredFormerMemberRecord = $tateRecords
                    ->contains(fn($record) => !$activeGroupUserIds->contains((int) $record->user_id)
                        && $this->recordHasEnteredShots($record));

                if ($hasEnteredFormerMemberRecord) {
                    [$snapshotSlots, $snapshotTateSize] = $this->officialSlotsFromRecordSnapshots(
                        $tateRecords,
                        $tateSize,
                        $officialCompactEmptySlots
                    );
                    $officialTateSlots->put($tateNo, $snapshotSlots);
                    $officialTateSizes->put($tateNo, $snapshotTateSize);
                    continue;
                }

                $officialTateSlots->put($tateNo, $displayLineupSlots);
                $officialTateSizes->put($tateNo, $displayLineupTateSize);
                continue;
            }

            if ($isCurrentSheet && $lineupSlots->isEmpty()) {
                if ($tateRecords->isNotEmpty()) {
                    [$snapshotSlots, $snapshotTateSize] = $this->officialSlotsFromRecordSnapshots(
                        $tateRecords,
                        $tateSize,
                        $officialCompactEmptySlots
                    );
                    $officialTateSlots->put($tateNo, $snapshotSlots);
                    $officialTateSizes->put($tateNo, $snapshotTateSize);
                    continue;
                }

                $officialTateSlots->put($tateNo, collect());
                $officialTateSizes->put($tateNo, $tateSize);
                continue;
            }

            if ($tateRecords->isEmpty()) {
                $officialTateSlots->put($tateNo, $displayLineupSlots);
                $officialTateSizes->put($tateNo, $displayLineupTateSize);
                continue;
            }

            [$snapshotSlots, $snapshotTateSize] = $this->officialSlotsFromRecordSnapshots(
                $tateRecords,
                $tateSize,
                $officialCompactEmptySlots
            );
            $officialTateSlots->put($tateNo, $snapshotSlots);
            $officialTateSizes->put($tateNo, $snapshotTateSize);
        }
        $lineupSlots = $displayLineupSlots;

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
        $latestMatchAssignmentsByRecordId = $this->latestMatchAssignmentsByRecordId($group, $date);
        $officialMatchTeamControls = $this->officialMatchTeamControls($group, $date);

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
            'canSwitchOfficialSheet',
            'canEditGroupRecords',
            'matchSelection',
            'latestMatchAssignmentsByRecordId',
            'officialMatchTeamControls',
            'officialCompactEmptySlots',
            'officialCompactEmptySlotsExplicit'
        ));
    }

    private function showMatchRecords(Request $request, Group $group, string $date)
    {
        $groupId = $group->id;
        $canEditGroupRecords = true;
        $matchAttendanceByUserId = $this->attendanceMembersByUserId($group, $date);
        $teams = MatchTeam::withTrashed()
            ->with(['members' => function ($q) use ($date) {
                $q->where('date', $date)->with(['user', 'officialRecord.shots']);
            }])
            ->where('group_id', $groupId)
            ->where(function ($q) use ($date) {
                $q->whereNull('deleted_at')
                    ->orWhereHas('records', function ($recordQuery) use ($date) {
                        $recordQuery->where('date', $date)
                            ->whereHas('shots', function ($shotQuery) {
                                $shotQuery->whereNotNull('result')
                                    ->orWhereNotNull('numeric_score');
                            });
                    })
                    ->orWhereHas('members', function ($memberQuery) use ($date) {
                        $memberQuery->where('date', $date)
                            ->whereNotNull('official_record_id');
                    });
            })
            ->orderBy('id')
            ->get();

        $teamIds = $teams->pluck('id');
        $legacyRecordRows = Record::with(['shots', 'user'])
            ->where('date', $date)
            ->where('practice_type', 'match')
            ->whereIn('match_team_id', $teamIds)
            ->get();
        $legacyRecords = $legacyRecordRows
            ->groupBy('match_team_id')
            ->map(fn($teamRecords) => $teamRecords->groupBy('user_id'));
        $legacyTatesByTeam = $legacyRecordRows
            ->groupBy('match_team_id')
            ->map(fn($teamRecords) => $teamRecords->pluck('tate_no')->unique()->sort()->values());
        $matchTateMetaRows = MatchTateMeta::whereIn('match_team_id', $teamIds)
            ->where('date', $date)
            ->get();
        $matchTateMetaTates = $matchTateMetaRows
            ->groupBy('match_team_id')
            ->map(fn($teamMetas) => $teamMetas->pluck('tate_no')->unique()->sort()->values());
        $officialSheetModes = DB::table('official_record_sheets')
            ->where('group_id', $groupId)
            ->where('date', $date)
            ->pluck('scoring_mode', 'sheet_no');

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
                ->merge($legacyTatesByTeam->get($team->id, collect()))
                ->merge($matchTateMetaTates->get($team->id, collect()))
                ->unique()
                ->sort()
                ->values();

            if (!$team->trashed()) {
                $teamTates = $teamTates
                    ->push(1)
                    ->unique()
                    ->sort()
                    ->values();
            }

            $matchTeamTates->put($team->id, $teamTates);

            if ($team->members->isNotEmpty()) {
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
                        $legacyRecord = $member
                            ? (($legacyRecords->get($team->id, collect())->get($member->user_id, collect()))
                                ->where('tate_no', $tateNo)
                                ->first())
                            : null;
                        $record = $member?->officialRecord ?: $legacyRecord;
                        $recordSource = $member?->official_record_id
                            ? 'official'
                            : ($legacyRecord ? 'match' : null);
                        $scoringMode = $recordSource === 'official'
                            ? ($officialSheetModes->get((int) ($record?->official_sheet_no ?? 1)) ?? 'hit_miss')
                            : null;

                        $slots->push((object) [
                            'position' => $pos,
                            'member' => $member,
                            'user' => $member?->user,
                            'is_empty' => is_null($member),
                            'record' => $record,
                            'record_source' => $recordSource,
                            'scoring_mode' => $scoringMode,
                            'official_sheet_no' => $recordSource === 'official' ? $record?->official_sheet_no : null,
                            'official_tate_no' => $recordSource === 'official' ? $record?->tate_no : null,
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
                        'record' => null,
                        'record_source' => null,
                        'scoring_mode' => null,
                        'official_sheet_no' => null,
                        'official_tate_no' => null,
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

        $records = $legacyRecords;

        $hitCounts = [];
        $matchTateMetas = $matchTateMetaRows
            ->groupBy('match_team_id')
            ->map(fn($teamMetas) => $teamMetas->keyBy('tate_no'));

        foreach ($teams as $team) {
            $hitCounts[$team->id] = [];

            foreach (($matchTeamUsers->get($team->id, collect()))->unique('id')->values() as $user) {
                $hitCounts[$team->id][$user->id] = 0;

                foreach (($matchTeamSlots->get($team->id, collect())) as $slots) {
                    foreach ($slots as $slot) {
                        if ((int) ($slot->user?->id ?? 0) !== (int) $user->id || !$slot->record) {
                            continue;
                        }

                        $hitCounts[$team->id][$user->id] += $slot->record->shots
                            ->where('result', 'hit')
                            ->count();
                    }
                }
            }
        }

        $month = $request->month ?? \Carbon\Carbon::parse($date)->format('Y-m');
        $prevMonth = \Carbon\Carbon::parse($month . '-01')->subMonth()->format('Y-m');
        $nextMonth = \Carbon\Carbon::parse($month . '-01')->addMonth()->format('Y-m');

        $memberLineupDates = MatchTeamMember::whereIn('match_team_id', MatchTeam::withTrashed()->where('group_id', $groupId)->pluck('id'))
            ->where(function ($query) {
                $query->whereNotNull('position')
                    ->orWhereNotNull('official_record_id');
            })
            ->whereYear('date', \Carbon\Carbon::parse($month . '-01')->year)
            ->whereMonth('date', \Carbon\Carbon::parse($month . '-01')->month)
            ->pluck('date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();
        $legacyLineupDates = Record::where('practice_type', 'match')
            ->whereIn('match_team_id', MatchTeam::withTrashed()->where('group_id', $groupId)->pluck('id'))
            ->whereHas('shots', function ($q) {
                $q->whereNotNull('result')
                    ->orWhereNotNull('numeric_score');
            })
            ->whereYear('date', \Carbon\Carbon::parse($month . '-01')->year)
            ->whereMonth('date', \Carbon\Carbon::parse($month . '-01')->month)
            ->pluck('date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();
        $lineupDates = collect($memberLineupDates)
            ->merge($legacyLineupDates)
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
            'matchAttendanceByUserId',
            'canEditGroupRecords'
        ));
    }

    public function addTate(Request $request, $groupId)
    {
        return $this->addTateForType($request, $groupId, 'official');
    }

    private function createInitialOfficialSheetRecordsIfNeeded(
        Group $group,
        string $date,
        int $activeSheetNo,
        $userIds,
        $groupUserIds,
        int $maxTatesPerPage,
        $lineupSnapshotsByUserId
    ): void {
        $userIds = collect($userIds)->values();
        $groupUserIds = collect($groupUserIds)->values();

        if ($userIds->isEmpty() || $groupUserIds->isEmpty()) {
            return;
        }

        $currentSheetTates = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', $activeSheetNo)
            ->pluck('tate_no')
            ->unique()
            ->sort()
            ->values();

        if ($currentSheetTates->count() >= $maxTatesPerPage) {
            return;
        }

        $maxTate = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->max('tate_no');
        $startTate = $maxTate ? $maxTate + 1 : 1;
        $endTate = (int) ceil($startTate / $maxTatesPerPage) * $maxTatesPerPage;
        $remainingSlots = $maxTatesPerPage - $currentSheetTates->count();
        $tates = collect(range($startTate, $endTate))
            ->take($remainingSlots)
            ->values();

        DB::table('official_record_sheets')->updateOrInsert(
            [
                'group_id' => $group->id,
                'date' => $date,
                'sheet_no' => $activeSheetNo,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->ensureRecordsWithShots(
            $userIds,
            $date,
            $tates,
            'official',
            null,
            $lineupSnapshotsByUserId,
            true,
            $activeSheetNo
        );
    }

    private function trimEmptyOfficialTatesAfterLastEntered($groupUserIds, string $date, int $sheetNo): void
    {
        $latestEnteredTate = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', $sheetNo)
            ->whereHas('shots', function ($query) {
                $query->whereNotNull('result')
                    ->orWhereNotNull('numeric_score');
            })
            ->max('tate_no');

        if (!$latestEnteredTate) {
            return;
        }

        $recordIds = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', $sheetNo)
            ->where('tate_no', '>', $latestEnteredTate)
            ->whereDoesntHave('shots', function ($query) {
                $query->whereNotNull('result')
                    ->orWhereNotNull('numeric_score');
            })
            ->pluck('id');

        if ($recordIds->isEmpty()) {
            return;
        }

        Shot::whereIn('record_id', $recordIds)->delete();
        Record::whereIn('id', $recordIds)->delete();
    }

    private function deleteEmptyOfficialSheetRecords($groupUserIds, string $date, int $activeSheetNo): void
    {
        $recordIds = Record::whereIn('user_id', $groupUserIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->where('official_sheet_no', $activeSheetNo)
            ->whereDoesntHave('shots', function ($query) {
                $query
                    ->whereNotNull('result')
                    ->orWhereNotNull('numeric_score');
            })
            ->pluck('id');

        if ($recordIds->isEmpty()) {
            return;
        }

        Shot::whereIn('record_id', $recordIds)->delete();
        Record::whereIn('id', $recordIds)->delete();
    }

    public function switchOfficialSheet(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId, true, true);

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
                $query->whereNotNull('result')
                    ->orWhereNotNull('numeric_score');
            })
            ->exists();

        if (!$hasEnteredOfficialShots) {
            $compactQuery = $request->has('compact_empty_slots')
                ? '&compact_empty_slots=' . ($request->boolean('compact_empty_slots', true) ? '1' : '0')
                : '';

            return redirect("/group/{$groupId}/records?date={$date}&sheet_no={$activeSheetNo}{$compactQuery}");
        }

        $this->trimEmptyOfficialTatesAfterLastEntered($activeGroupUserIds, $date, $activeSheetNo);
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

        $compactQuery = $request->has('compact_empty_slots')
            ? '&compact_empty_slots=' . ($request->boolean('compact_empty_slots', true) ? '1' : '0')
            : '';

        return redirect("/group/{$groupId}/records?date={$date}&sheet_no={$nextSheetNo}{$compactQuery}");
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

        $month = \Carbon\Carbon::parse($date)->format('Y-m');
        $memberMaxTate = (int) ($team->members()->where('date', $date)->max('tate_no') ?? 0);
        $metaMaxTate = (int) (MatchTateMeta::where('match_team_id', $team->id)
            ->where('date', $date)
            ->max('tate_no') ?? 0);
        $legacyMaxTate = (int) (Record::where('match_team_id', $team->id)
            ->where('date', $date)
            ->where('practice_type', 'match')
            ->max('tate_no') ?? 0);
        $maxTate = max(1, $memberMaxTate, $metaMaxTate, $legacyMaxTate);
        $newTate = $maxTate + 1;
        $sourceTate = $memberMaxTate > 0 ? $memberMaxTate : null;
        $previousMeta = null;

        if (!$sourceTate) {
            $message = '先に1立目でメンバーを選択してから、＋立を押してください。';

            return redirect($this->matchAddTateReturnUrl($request, $groupId, $date, $month, $team->id, 1))
                ->with('error', $message)
                ->with('error_alert', $message);
        }

        if ($sourceTate) {
            $previousMeta = MatchTateMeta::where('match_team_id', $team->id)
                ->where('date', $date)
                ->where('tate_no', $sourceTate)
                ->first();

            $now = now();
            $linkedOfficialRecordIds = MatchTeamMember::where('match_team_id', $team->id)
                ->where('date', $date)
                ->whereNotNull('official_record_id')
                ->pluck('official_record_id');
            $sourceMembers = $team->members()
                ->where('date', $date)
                ->where('tate_no', $sourceTate)
                ->with(['officialRecord.shots', 'user'])
                ->get();
            $activeSourceMembers = $sourceMembers
                ->filter(fn($member) => !is_null($member->position)
                    && !$member->is_absent
                    && !$member->is_late);

            if ($activeSourceMembers->isEmpty()) {
                $message = "{$sourceTate}立目にメンバーが選択されていません。先にメンバーを選択してから、＋立を押してください。";

                return redirect($this->matchAddTateReturnUrl($request, $groupId, $date, $month, $team->id, $sourceTate))
                    ->with('error', $message)
                    ->with('error_alert', $message);
            }

            $hasEnteredSourceTateScore = $activeSourceMembers
                ->contains(fn($member) => $member->officialRecord
                    && $member->officialRecord->shots->contains(fn($shot) => !is_null($shot->result) || !is_null($shot->numeric_score)));
            $hasEnteredLegacySourceTateScore = Record::where('match_team_id', $team->id)
                ->where('date', $date)
                ->where('practice_type', 'match')
                ->where('tate_no', $sourceTate)
                ->whereHas('shots', function ($query) {
                    $query->whereNotNull('result')
                        ->orWhereNotNull('numeric_score');
                })
                ->exists();

            if (!$hasEnteredSourceTateScore && !$hasEnteredLegacySourceTateScore) {
                $message = "{$sourceTate}立目の的中を入力してから、＋立を押してください。";

                return redirect($this->matchAddTateReturnUrl($request, $groupId, $date, $month, $team->id, $sourceTate))
                    ->with('error', $message)
                    ->with('error_alert', $message);
            }

            $missingNextOfficialMembers = collect();
            $plannedMembers = $sourceMembers
                ->map(function ($member) use (&$linkedOfficialRecordIds, $missingNextOfficialMembers) {
                    $shouldLinkNextRecord = !is_null($member->position)
                        && !$member->is_absent
                        && !$member->is_late;
                    $nextOfficialRecord = $shouldLinkNextRecord
                        ? $this->nextOfficialRecordAfter($member->officialRecord, $linkedOfficialRecordIds)
                        : null;

                    if ($shouldLinkNextRecord && !$nextOfficialRecord) {
                        $missingNextOfficialMembers->push($member);
                    }

                    if ($nextOfficialRecord) {
                        $linkedOfficialRecordIds->push($nextOfficialRecord->id);
                    }

                    return (object) [
                        'member' => $member,
                        'next_official_record' => $nextOfficialRecord,
                    ];
                });

            if ($missingNextOfficialMembers->isNotEmpty()) {
                $month = \Carbon\Carbon::parse($date)->format('Y-m');
                $names = $missingNextOfficialMembers
                    ->pluck('user.name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode('、');
                $message = $names
                    ? "{$names} の次に連動できる正規連がありません。5立目以降に進む場合は、正規連で次ページを追加してからもう一度＋立を押してください。"
                    : '次に連動できる正規連がありません。5立目以降に進む場合は、正規連で次ページを追加してからもう一度＋立を押してください。';

                return redirect($this->matchAddTateReturnUrl($request, $groupId, $date, $month, $team->id, $sourceTate))
                    ->with('error', $message)
                    ->with('error_alert', $message);
            }

            $memberInserts = $plannedMembers
                ->map(function ($plannedMember) use ($team, $date, $newTate, $now) {
                    $member = $plannedMember->member;
                    $nextOfficialRecord = $plannedMember->next_official_record;

                    if ($nextOfficialRecord) {
                        $this->ensureRecordHasFourShots($nextOfficialRecord);
                    }

                    return [
                        'match_team_id' => $team->id,
                        'date' => $date,
                        'user_id' => $member->user_id,
                        'tate_no' => $newTate,
                        'position' => $member->position,
                        'official_record_id' => $nextOfficialRecord?->id,
                        'is_absent' => $member->is_absent,
                        'is_late' => $member->is_late,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })
                ->all();

            if (!empty($memberInserts)) {
                DB::table('match_team_members')->insert($memberInserts);
            }

        }

        MatchTateMeta::updateOrCreate(
            [
                'match_team_id' => $team->id,
                'date' => $date,
                'tate_no' => $newTate,
            ],
            [
                'elapsed_seconds' => 0,
                'is_timer_running' => false,
                'timer_started_at' => null,
                'scoring_mode' => $previousMeta?->scoring_mode ?? 'hit_miss',
            ]
        );

        return redirect($this->matchAddTateReturnUrl($request, $groupId, $date, $month, $team->id, $newTate));
    }

    public function updateOfficialScoringMode(Request $request, $groupId)
    {
        $this->checkGroupAccess($groupId, true, true);

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

    public function assignMatchOfficialRecord(Request $request, MatchTeam $team)
    {
        $this->checkGroupAccess($team->group_id);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'tate_no' => ['required', 'integer', 'min:1'],
            'position' => ['required', 'integer', 'min:1'],
            'record_id' => ['required', 'integer', 'exists:records,id'],
        ]);

        if ((int) $validated['position'] > (int) $team->tate_size) {
            return response()->json([
                'ok' => false,
                'message' => '指定された立順位置がチーム人数を超えています。',
            ], 422);
        }

        $groupUserIds = Group::findOrFail($team->group_id)
            ->users()
            ->where('is_admin', false)
            ->pluck('users.id');

        $record = Record::with(['shots', 'user'])
            ->whereKey($validated['record_id'])
            ->whereIn('user_id', $groupUserIds)
            ->where('date', $validated['date'])
            ->where('practice_type', 'official')
            ->firstOrFail();

        $this->ensureRecordHasFourShots($record);

        DB::transaction(function () use ($team, $validated, $record) {
            MatchTeamMember::where('match_team_id', $team->id)
                ->where('date', $validated['date'])
                ->where('official_record_id', $record->id)
                ->where(function ($query) use ($validated) {
                    $query->where('tate_no', '<>', $validated['tate_no'])
                        ->orWhere('position', '<>', $validated['position'])
                        ->orWhereNull('position');
                })
                ->update([
                    'position' => null,
                    'official_record_id' => null,
                    'updated_at' => now(),
                ]);

            MatchTeamMember::updateOrCreate(
                [
                    'match_team_id' => $team->id,
                    'date' => $validated['date'],
                    'tate_no' => $validated['tate_no'],
                    'position' => $validated['position'],
                ],
                [
                    'user_id' => $record->user_id,
                    'official_record_id' => $record->id,
                    'is_absent' => false,
                    'is_late' => false,
                ]
            );
        });

        $month = \Carbon\Carbon::parse($validated['date'])->format('Y-m');
        $nextPosition = (int) $validated['position'] < (int) $team->tate_size
            ? (int) $validated['position'] + 1
            : null;
        $nextSheetNo = max(1, (int) ($record->official_sheet_no ?? 1));
        $returnToOfficial = $request->input('return_to') === 'official';
        $returnToQuery = $returnToOfficial ? '&return_to=official' : '';
        $compactQuery = $request->has('compact_empty_slots')
            ? '&compact_empty_slots=' . ($request->boolean('compact_empty_slots', true) ? '1' : '0')
            : '';
        $nextUrl = $nextPosition
            ? "/group/{$team->group_id}/records?date={$validated['date']}&month={$month}&sheet_no={$nextSheetNo}&match_team_id={$team->id}&match_tate_no={$validated['tate_no']}&match_position={$nextPosition}{$returnToQuery}{$compactQuery}"
            : ($returnToOfficial
                ? "/group/{$team->group_id}/records?date={$validated['date']}&month={$month}&sheet_no={$nextSheetNo}{$compactQuery}#official-match-team-controls"
                : "/group/{$team->group_id}/match-records?date={$validated['date']}&month={$month}&team_id={$team->id}#match-team-{$team->id}");

        return response()->json([
            'ok' => true,
            'next_position' => $nextPosition,
            'next_url' => $nextUrl,
            'assigned' => [
                'position' => (int) $validated['position'],
                'record_id' => (int) $record->id,
                'user_name' => $record->user?->name,
                'official_tate_no' => (int) $record->tate_no,
                'official_sheet_no' => (int) ($record->official_sheet_no ?? 1),
            ],
        ]);
    }

    private function addTateForType(Request $request, $groupId, string $practiceType)
    {
        $this->checkGroupAccess($groupId, $practiceType === 'official', $practiceType === 'official');

        $group = Group::with('users')->findOrFail($groupId);
        $date = $request->date ?? date('Y-m-d');
        $activeSheetNo = max(1, (int) ($request->sheet_no ?? 1));
        $maxTatesPerPage = max(1, (int) ($group->official_tates_per_page ?? 5));
        $redirectPath = $practiceType === 'match'
            ? "/group/{$groupId}/match-records"
            : "/group/{$groupId}/records";
        $compactQuery = $practiceType === 'official' && $request->has('compact_empty_slots')
            ? '&compact_empty_slots=' . ($request->boolean('compact_empty_slots', true) ? '1' : '0')
            : '';

        $lineup = Lineup::with('members.user')
            ->where('group_id', $groupId)
            ->where('date', $date)
            ->first();

        if (!$lineup) {
            return redirect("{$redirectPath}?date={$date}{$compactQuery}");
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
            ->sortBy('position')
            ->unique('user_id')
            ->values();
        $users = $placedMembers
            ->sortBy('position')
            ->pluck('user')
            ->filter()
            ->values();

        if ($users->isEmpty()) {
            return redirect("{$redirectPath}?date={$date}{$compactQuery}");
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
            return redirect("{$redirectPath}?date={$date}&sheet_no={$activeSheetNo}{$compactQuery}");
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

        return redirect("{$redirectPath}?date={$date}{$sheetQuery}{$compactQuery}");
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
            $this->checkGroupAccess($groupId, $record->practice_type === 'official', $record->practice_type === 'official');
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

    private function checkGroupAccess($groupId, bool $requiresGroupRecordPermission = false, bool $requiresGroupRecordEditPermission = false): void
    {
        $user = auth()->user();

        $group = Group::findOrFail($groupId);

        if (!$user || ($user->username !== 'KANRI' && !$user->groups()->where('groups.id', $groupId)->exists())) {
            abort(403, 'このグループにはアクセスできません');
        }

        if ($this->isGroupHostOrAdmin($group, $user)) {
            return;
        }

        if (
            ($requiresGroupRecordPermission || $requiresGroupRecordEditPermission)
            && $user->username !== 'KANRI'
            && !$group->show_group_records_to_members
        ) {
            abort(403, 'ホスト以外はグループ記録画面を表示できません');
        }

        if (
            $requiresGroupRecordEditPermission
            && !$group->allow_members_edit_group_records
        ) {
            abort(403, 'ホスト以外はグループ記録を編集できません');
        }
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

    private function syncLineupMembers(Lineup $lineup, Group $group): void
    {
        LineupMember::ensureForLineupUsers(
            $lineup,
            $group->users->where('is_admin', false)
        );
    }

    private function latestMatchAssignmentsByRecordId(Group $group, string $date)
    {
        $teamColors = [
            '#dc3545',
            '#0d6efd',
            '#198754',
            '#fd7e14',
            '#6f42c1',
            '#0aa2c0',
            '#d63384',
            '#6c757d',
        ];

        return MatchTeam::withTrashed()
            ->with(['members' => fn($query) => $query->where('date', $date)])
            ->where('group_id', $group->id)
            ->whereHas('members', fn($query) => $query->where('date', $date))
            ->orderBy('id')
            ->get()
            ->values()
            ->flatMap(function ($team, int $teamIndex) use ($teamColors) {
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
                    ->whereNotNull('official_record_id')
                    ->where('is_absent', false)
                    ->where('is_late', false)
                    ->sortBy('position')
                    ->map(fn($member) => [
                        'official_record_id' => (int) $member->official_record_id,
                        'user_id' => (int) $member->user_id,
                        'team_id' => (int) $team->id,
                        'team_name' => $team->name,
                        'tate_no' => (int) $latestTateNo,
                        'position' => (int) $member->position,
                        'tate_size' => (int) $team->tate_size,
                        'color' => $teamColors[$teamIndex % count($teamColors)],
                    ]);
            })
            ->groupBy('official_record_id')
            ->map(fn($assignments) => $assignments->values());
    }

    private function officialMatchTeamControls(Group $group, string $date)
    {
        $teamColors = [
            '#dc3545',
            '#0d6efd',
            '#198754',
            '#fd7e14',
            '#6f42c1',
            '#0aa2c0',
            '#d63384',
            '#6c757d',
        ];

        $teams = MatchTeam::with([
                'members' => fn($query) => $query->where('date', $date)->with('officialRecord.shots'),
                'tateMetas' => fn($query) => $query->where('date', $date),
            ])
            ->where('group_id', $group->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->values();

        if ($teams->isEmpty()) {
            return collect();
        }

        $legacyMaxTates = Record::where('date', $date)
            ->where('practice_type', 'match')
            ->whereIn('match_team_id', $teams->pluck('id'))
            ->select('match_team_id', DB::raw('MAX(tate_no) as max_tate_no'))
            ->groupBy('match_team_id')
            ->pluck('max_tate_no', 'match_team_id');
        $legacyRecords = Record::with('shots')
            ->where('date', $date)
            ->where('practice_type', 'match')
            ->whereIn('match_team_id', $teams->pluck('id'))
            ->get()
            ->groupBy('match_team_id');

        return $teams->map(function ($team, int $teamIndex) use ($teamColors, $legacyMaxTates, $legacyRecords) {
            $latestTateNo = max(
                1,
                (int) ($team->members->pluck('tate_no')->filter()->max() ?? 0),
                (int) ($team->tateMetas->pluck('tate_no')->filter()->max() ?? 0),
                (int) ($legacyMaxTates->get($team->id) ?? 0),
            );
            $meta = $team->tateMetas->firstWhere('tate_no', $latestTateNo);
            $timerStartedAt = $meta?->timer_started_at
                ? \Carbon\Carbon::parse($meta->timer_started_at)
                : null;
            $isTimerRunning = (bool) (($meta?->is_timer_running ?? false) && $timerStartedAt);
            $elapsedSeconds = (int) ($meta?->elapsed_seconds ?? 0);

            if ($isTimerRunning) {
                $elapsedSeconds += max(0, now()->timestamp - $timerStartedAt->timestamp);
            }

            $officialRecords = $team->members
                ->where('tate_no', $latestTateNo)
                ->whereNotNull('position')
                ->where('is_absent', false)
                ->where('is_late', false)
                ->pluck('officialRecord')
                ->filter();
            $teamLegacyRecords = ($legacyRecords->get($team->id, collect()))
                ->where('tate_no', $latestTateNo);
            $hitCount = $officialRecords
                ->merge($teamLegacyRecords)
                ->unique('id')
                ->sum(fn($record) => $record->shots->where('result', 'hit')->count());

            return (object) [
                'team' => $team,
                'team_id' => (int) $team->id,
                'team_name' => $team->name,
                'tate_no' => $latestTateNo,
                'hit_count' => $hitCount,
                'elapsed_seconds' => $elapsedSeconds,
                'elapsed_label' => sprintf('%02d:%02d', floor($elapsedSeconds / 60), $elapsedSeconds % 60),
                'is_timer_running' => $isTimerRunning,
                'color' => $teamColors[$teamIndex % count($teamColors)],
            ];
        });
    }

    private function matchAddTateReturnUrl(Request $request, int $groupId, string $date, string $month, int $teamId, int $tateNo): string
    {
        if ($request->input('return_to') === 'official') {
            $sheetNo = max(1, (int) ($request->input('sheet_no') ?? 1));
            $compactQuery = $request->has('compact_empty_slots')
                ? '&compact_empty_slots=' . ($request->boolean('compact_empty_slots', true) ? '1' : '0')
                : '';

            return "/group/{$groupId}/records?date={$date}&month={$month}&sheet_no={$sheetNo}{$compactQuery}#official-match-team-controls";
        }

        return "/group/{$groupId}/match-records?date={$date}&month={$month}&team_id={$teamId}&tate_no={$tateNo}#match-team-{$teamId}";
    }

    private function enteredOfficialRecordUserIds($userIds, string $date)
    {
        $userIds = collect($userIds)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return Record::whereIn('user_id', $userIds)
            ->where('date', $date)
            ->where('practice_type', 'official')
            ->whereHas('shots', function ($query) {
                $query->whereNotNull('result')
                    ->orWhereNotNull('numeric_score');
            })
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function recordHasEnteredShots(Record $record): bool
    {
        return $record->shots
            ->contains(fn($shot) => !is_null($shot->result) || !is_null($shot->numeric_score));
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

    private function officialSlotsFromRecordSnapshots($tateRecords, int $fallbackTateSize, bool $compactEmptySlots): array
    {
        $tateRecords = collect($tateRecords)
            ->filter(fn($record) => !is_null($record->lineup_position))
            ->values();
        $snapshotTateSize = max(1, (int) ($tateRecords->pluck('lineup_tate_size')->filter()->first() ?? $fallbackTateSize));
        $maxPosition = max((int) $tateRecords->max('lineup_position'), $snapshotTateSize);
        $totalSlots = (int) ceil($maxPosition / $snapshotTateSize) * $snapshotTateSize;
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

        return [
            $this->officialDisplaySlots($slots, $compactEmptySlots, $snapshotTateSize),
            $this->officialDisplayTateSize($slots, $compactEmptySlots, $snapshotTateSize),
        ];
    }

    private function officialDisplaySlots($slots, bool $compactEmptySlots, int $tateSize)
    {
        $slots = collect($slots)->values();
        $tateSize = max(1, $tateSize);

        if (!$compactEmptySlots) {
            return $slots->map(function ($slot, $index) use ($tateSize) {
                $displaySlot = clone $slot;
                $displaySlot->display_tate_break = (($index + 1) % $tateSize) === 0;

                return $displaySlot;
            });
        }

        $displayPosition = 1;

        return $slots
            ->chunk($tateSize)
            ->flatMap(function ($tateSlots) use (&$displayPosition) {
                $tateSlots = $tateSlots->values();
                $lastFilledIndex = $tateSlots->search(fn($slot) => !$slot->is_empty && $slot->user);

                if ($lastFilledIndex === false) {
                    return collect();
                }

                foreach ($tateSlots as $index => $slot) {
                    if (!$slot->is_empty && $slot->user) {
                        $lastFilledIndex = $index;
                    }
                }

                $displaySlots = $tateSlots->take($lastFilledIndex + 1)->values();

                return $displaySlots->map(function ($slot, $index) use (&$displayPosition, $displaySlots) {
                    $displaySlot = clone $slot;
                    $displaySlot->position = $displayPosition++;
                    $displaySlot->display_tate_break = ($index + 1) === $displaySlots->count();

                    return $displaySlot;
                });
            })
            ->values();
    }

    private function officialDisplayTateSize($slots, bool $compactEmptySlots, int $tateSize): int
    {
        $slots = collect($slots)->values();
        $tateSize = max(1, $tateSize);

        if (!$compactEmptySlots) {
            return $tateSize;
        }

        return max(
            1,
            (int) $slots
                ->chunk($tateSize)
                ->map(function ($tateSlots) {
                    $tateSlots = $tateSlots->values();
                    $lastFilledIndex = false;

                    foreach ($tateSlots as $index => $slot) {
                        if (!$slot->is_empty && $slot->user) {
                            $lastFilledIndex = $index;
                        }
                    }

                    return $lastFilledIndex === false ? 0 : $lastFilledIndex + 1;
                })
                ->filter()
                ->max()
        );
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
            ->sortBy('position')
            ->unique('user_id')
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

    private function ensureRecordHasFourShots(Record $record): void
    {
        $existingShotNos = $record->shots
            ->pluck('shot_no')
            ->map(fn($shotNo) => (int) $shotNo)
            ->all();
        $now = now();
        $shotInserts = [];

        for ($shotNo = 1; $shotNo <= 4; $shotNo++) {
            if (in_array($shotNo, $existingShotNos, true)) {
                continue;
            }

            $shotInserts[] = [
                'record_id' => $record->id,
                'shot_no' => $shotNo,
                'result' => null,
                'numeric_score' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($shotInserts)) {
            Shot::insert($shotInserts);
            $record->load('shots');
        }
    }

    private function nextOfficialRecordAfter(?Record $record, $excludedRecordIds): ?Record
    {
        if (!$record) {
            return null;
        }

        $excludedRecordIds = collect($excludedRecordIds)
            ->filter()
            ->map(fn($recordId) => (int) $recordId)
            ->unique()
            ->values();
        $sheetNo = max(1, (int) ($record->official_sheet_no ?? 1));
        $tateNo = (int) $record->tate_no;

        return Record::with('shots')
            ->where('user_id', $record->user_id)
            ->where('date', $record->date)
            ->where('practice_type', 'official')
            ->where('id', '<>', $record->id)
            ->when($excludedRecordIds->isNotEmpty(), fn($query) => $query->whereNotIn('id', $excludedRecordIds))
            ->where(function ($query) use ($sheetNo, $tateNo) {
                $query->where('official_sheet_no', '>', $sheetNo)
                    ->orWhere(function ($sameSheetQuery) use ($sheetNo, $tateNo) {
                        $sameSheetQuery->where('official_sheet_no', $sheetNo)
                            ->where('tate_no', '>', $tateNo);
                    })
                    ->orWhere(function ($legacyQuery) use ($tateNo) {
                        $legacyQuery->whereNull('official_sheet_no')
                            ->where('tate_no', '>', $tateNo);
                    });
            })
            ->orderByRaw('COALESCE(official_sheet_no, 1)')
            ->orderBy('tate_no')
            ->first();
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
