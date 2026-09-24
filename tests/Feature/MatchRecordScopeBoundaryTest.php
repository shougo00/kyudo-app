<?php

use App\Models\Group;
use App\Models\MatchTeam;
use App\Models\Record;
use App\Models\User;

beforeEach(function () {
    $this->host = User::factory()->create(['is_admin' => true]);
    $this->member = User::factory()->create(['is_admin' => false]);
    $this->group = Group::create([
        'name' => 'Match scope boundary',
        'host_user_id' => $this->host->id,
        'invite_code' => '9892',
    ]);
    $this->group->users()->attach([$this->host->id, $this->member->id]);
    $this->actingAs($this->host);
});

it('rejects official record assignment to a self team without changing existing records', function () {
    $date = '2026-09-24';
    $team = MatchTeam::create([
        'group_id' => $this->group->id, 'record_scope' => 'self',
        'date' => $date, 'name' => 'Self team', 'division' => 'mixed', 'tate_size' => 3,
    ]);
    $selfRecord = Record::create([
        'user_id' => $this->member->id, 'date' => $date, 'practice_type' => 'self', 'tate_no' => 1,
    ]);
    $assignment = $team->members()->create([
        'date' => $date, 'user_id' => $this->member->id, 'tate_no' => 1,
        'position' => 1, 'official_record_id' => $selfRecord->id,
        'is_absent' => false, 'is_late' => false,
    ]);
    $officialRecord = Record::create([
        'user_id' => $this->member->id, 'date' => $date, 'practice_type' => 'official',
        'tate_no' => 1, 'official_sheet_no' => 1,
    ]);
    $payload = ['date' => $date, 'tate_no' => 1, 'position' => 1, 'record_id' => $officialRecord->id];

    $this->postJson("/match-teams/{$team->id}/official-record", $payload)
        ->assertUnprocessable()
        ->assertJson(['ok' => false, 'message' => '自主練の試合形式記録に正規連の記録は登録できません。']);
    expect($assignment->fresh()->official_record_id)->toBe($selfRecord->id)
        ->and($team->members()->count())->toBe(1)
        ->and($officialRecord->shots()->count())->toBe(0);

    $officialTeam = MatchTeam::create([
        'group_id' => $this->group->id, 'record_scope' => 'official',
        'date' => $date, 'name' => 'Official team', 'division' => 'mixed', 'tate_size' => 3,
    ]);
    $this->postJson("/match-teams/{$officialTeam->id}/official-record", $payload)->assertOk()->assertJson(['ok' => true]);
    expect($officialTeam->members()->firstOrFail()->official_record_id)->toBe($officialRecord->id);
});

it('marks only dates with assignments or scored legacy records in the calendar scope', function ($scope, $path) {
    $teams = collect();
    foreach (['self', 'official'] as $teamScope) {
        $team = MatchTeam::create([
            'group_id' => $this->group->id, 'record_scope' => $teamScope,
            'date' => '2026-09-01', 'name' => $teamScope, 'division' => 'mixed', 'tate_size' => 3,
        ]);
        $teams->put($teamScope, $team);
        // Placement alone is enough, even without any shots entered.
        $team->members()->create([
            'date' => $teamScope === $scope ? '2026-09-02' : '2026-09-03',
            'user_id' => $this->member->id, 'tate_no' => 1, 'position' => 1,
            'is_absent' => false, 'is_late' => false,
        ]);
    }
    $team = $teams->get($scope);
    // A timer-only tate does not mark the calendar.
    $team->tateMetas()->create(['date' => '2026-09-05', 'tate_no' => 1, 'elapsed_seconds' => 30]);
    $linkedRecord = Record::create([
        'user_id' => $this->member->id, 'date' => '2026-09-06', 'practice_type' => $scope, 'tate_no' => 1,
    ]);
    $team->members()->create([
        'date' => '2026-09-06', 'user_id' => $this->member->id, 'tate_no' => 1,
        'position' => 1, 'official_record_id' => $linkedRecord->id, 'is_absent' => false, 'is_late' => false,
    ]);
    foreach (['2026-09-07' => 'hit', '2026-09-08' => null] as $date => $result) {
        $record = Record::create([
            'user_id' => $this->member->id, 'date' => $date, 'practice_type' => 'match',
            'match_team_id' => $team->id, 'tate_no' => 1,
        ]);
        $record->shots()->create(['shot_no' => 1, 'result' => $result]);
    }

    $this->get("/group/{$this->group->id}/{$path}?date=2026-09-01&month=2026-09&open=1")
        ->assertOk()
        ->assertViewHas('lineupDates', fn ($dates) => collect($dates)->sort()->values()->all() === ['2026-09-02', '2026-09-06', '2026-09-07'])
        ->assertSee('試合形式<br>記録あり', false)
        ->assertDontSee('正規連<br>立順あり', false);
})->with([
    'self' => ['self', 'self-match-records'],
    'official' => ['official', 'match-records'],
]);
