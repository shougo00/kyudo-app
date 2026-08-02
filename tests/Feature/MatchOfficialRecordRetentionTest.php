<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\MatchTeamMember;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;

function createOfficialRecordForMatchRetention(User $user, string $date, int $tateNo, int $sheetNo, bool $entered = false): Record
{
    $record = Record::create([
        'user_id' => $user->id,
        'date' => $date,
        'tate_no' => $tateNo,
        'practice_type' => 'official',
        'official_sheet_no' => $sheetNo,
    ]);

    foreach (range(1, 4) as $shotNo) {
        Shot::create([
            'record_id' => $record->id,
            'shot_no' => $shotNo,
            'result' => $entered && $shotNo === 1 ? 'hit' : null,
        ]);
    }

    return $record;
}

it('moves empty official records linked to a match tate onto the next official sheet', function () {
    $date = '2026-08-02';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'match-retention-host',
        'is_admin' => true,
    ]);
    $members = User::factory()
        ->count(2)
        ->sequence(
            ['name' => 'Archer A', 'username' => 'match-retention-a', 'is_admin' => false],
            ['name' => 'Archer B', 'username' => 'match-retention-b', 'is_admin' => false],
        )
        ->create();
    $group = Group::create([
        'name' => 'Match Retention Group',
        'host_user_id' => $host->id,
        'invite_code' => '6739',
        'official_tates_per_page' => 5,
    ]);
    $group->users()->attach($members->pluck('id')->push($host->id)->all());
    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 2,
    ]);
    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Team A',
        'division' => 'mixed',
        'tate_size' => 2,
    ]);
    $linkedOfficialRecords = collect();

    foreach ($members as $position => $member) {
        LineupMember::create([
            'lineup_id' => $lineup->id,
            'user_id' => $member->id,
            'position' => $position + 1,
            'is_absent' => false,
            'is_late' => false,
        ]);

        foreach (range(1, 4) as $tateNo) {
            createOfficialRecordForMatchRetention($member, $date, $tateNo, 1, true);
        }

        $linkedOfficialRecord = createOfficialRecordForMatchRetention($member, $date, 5, 1, false);
        $linkedOfficialRecords->push($linkedOfficialRecord);

        MatchTeamMember::create([
            'match_team_id' => $team->id,
            'date' => $date,
            'user_id' => $member->id,
            'tate_no' => 5,
            'position' => $position + 1,
            'official_record_id' => $linkedOfficialRecord->id,
            'is_absent' => false,
            'is_late' => false,
        ]);
    }

    $this->actingAs($host)
        ->post("/group/{$group->id}/records/switch-sheet", [
            'date' => $date,
            'sheet_no' => 1,
        ])
        ->assertRedirect("/group/{$group->id}/records?date={$date}&sheet_no=2");

    foreach ($linkedOfficialRecords as $linkedOfficialRecord) {
        expect(Record::whereKey($linkedOfficialRecord->id)->exists())->toBeTrue();
        expect((int) $linkedOfficialRecord->fresh()->official_sheet_no)->toBe(2);
    }

    $firstSheetTates = Record::whereIn('user_id', $members->pluck('id'))
        ->where('date', $date)
        ->where('practice_type', 'official')
        ->where('official_sheet_no', 1)
        ->pluck('tate_no')
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($firstSheetTates)->toBe([1, 2, 3, 4]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&sheet_no=2")
        ->assertOk()
        ->assertSee('5立〜9立', false)
        ->assertDontSee('Team A / 5立目', false);

    $secondSheetTates = Record::whereIn('user_id', $members->pluck('id'))
        ->where('date', $date)
        ->where('practice_type', 'official')
        ->where('official_sheet_no', 2)
        ->pluck('tate_no')
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($secondSheetTates)->toBe([5, 6, 7, 8, 9]);

    $linkedMemberRecordIds = MatchTeamMember::where('match_team_id', $team->id)
        ->where('date', $date)
        ->where('tate_no', 5)
        ->pluck('official_record_id')
        ->filter()
        ->sort()
        ->values();

    expect($linkedMemberRecordIds->all())->toBe($linkedOfficialRecords->pluck('id')->sort()->values()->all());
});
