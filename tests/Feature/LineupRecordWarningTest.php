<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\MatchTeamMember;
use App\Models\MatchTateMeta;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('uses the lineup page compact setting when showing empty slots on official records', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-official-compact-slots',
        'is_admin' => true,
    ]);
    $firstMember = User::factory()->create([
        'name' => 'First Member',
        'username' => 'first-official-compact-slots',
        'is_admin' => false,
    ]);
    $secondMember = User::factory()->create([
        'name' => 'Second Member',
        'username' => 'second-official-compact-slots',
        'is_admin' => false,
    ]);

    $group = Group::create([
        'name' => 'Official Compact Slots Group',
        'host_user_id' => $host->id,
        'invite_code' => '2461',
    ]);
    $group->users()->attach([$host->id, $firstMember->id, $secondMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 3,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $firstMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $secondMember->id,
        'position' => 3,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $compactResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}");

    $compactResponse->assertOk();
    $compactResponse->assertSee('empty-column', false);
    $compactResponse->assertSee('空き');
    $compactResponse->assertSee('First Member');
    $compactResponse->assertSee('Second Member');
    $this->assertMatchesRegularExpression(
        '/First Member[\s\S]*?空き[\s\S]*?Second Member/',
        $compactResponse->getContent()
    );

    $spacedResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&compact_empty_slots=0");

    $spacedResponse->assertOk();
    $spacedResponse->assertSee('empty-column', false);
    $spacedResponse->assertSee('空き');
    $spacedResponse->assertSee('compact_empty_slots=0', false);
});

it('uses the compacted member count as the official tate size when the last lineup slot is empty', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-official-nine-of-ten',
        'is_admin' => true,
    ]);
    $members = collect(range(1, 9))->map(fn($index) => User::factory()->create([
        'name' => "Member {$index}",
        'username' => "member-nine-of-ten-{$index}",
        'is_admin' => false,
    ]));

    $group = Group::create([
        'name' => 'Official Nine Of Ten Group',
        'host_user_id' => $host->id,
        'invite_code' => '2462',
    ]);
    $group->users()->attach($members->pluck('id')->push($host->id)->all());

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 10,
    ]);

    foreach ($members as $index => $member) {
        LineupMember::create([
            'lineup_id' => $lineup->id,
            'user_id' => $member->id,
            'position' => $index + 1,
            'is_absent' => false,
            'is_late' => false,
        ]);
    }

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}");

    $response->assertOk();
    $response->assertDontSee('empty-column', false);
    $this->assertMatchesRegularExpression(
        '/class="tate-user-name\s+tate-border"[\s\S]*?<span>9<\/span>[\s\S]*?Member 9/',
        $response->getContent()
    );
});

it('uses the selected official sheet when marking lineup members with entered records', function () {
    $date = '2026-07-25';
    $host = User::factory()->create(['username' => 'host']);
    $member = User::factory()->create(['username' => 'member']);
    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '1234',
    ]);

    $group->users()->attach([$host->id, $member->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 3,
    ]);
    $lineupMember = LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    DB::table('official_record_sheets')->insert([
        [
            'group_id' => $group->id,
            'date' => $date,
            'sheet_no' => 1,
            'scoring_mode' => 'hit_miss',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'group_id' => $group->id,
            'date' => $date,
            'sheet_no' => 2,
            'scoring_mode' => 'hit_miss',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $record = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
    ]);
    Shot::create([
        'record_id' => $record->id,
        'shot_no' => 1,
        'result' => 'hit',
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}&sheet_no=2");

    $response->assertOk();
    $this->assertMatchesRegularExpression(
        '/data-id="' . $lineupMember->id . '"[\s\S]*?data-has-record="0"/',
        $response->getContent()
    );
});

it('marks lineup members when the latest official sheet has entered records', function () {
    $date = '2026-07-25';
    $host = User::factory()->create(['username' => 'host']);
    $member = User::factory()->create(['username' => 'member']);
    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '5678',
    ]);

    $group->users()->attach([$host->id, $member->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 3,
    ]);
    $lineupMember = LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    DB::table('official_record_sheets')->insert([
        'group_id' => $group->id,
        'date' => $date,
        'sheet_no' => 2,
        'scoring_mode' => 'hit_miss',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $record = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 2,
    ]);
    Shot::create([
        'record_id' => $record->id,
        'shot_no' => 1,
        'result' => 'hit',
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}&sheet_no=2");

    $response->assertOk();
    $this->assertMatchesRegularExpression(
        '/data-id="' . $lineupMember->id . '"[\s\S]*?data-has-record="1"/',
        $response->getContent()
    );
});

it('keeps the active official sheet number when linking from records to lineup', function () {
    $date = '2026-07-25';
    $host = User::factory()->create(['username' => 'host']);
    $member = User::factory()->create(['username' => 'member']);
    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '9012',
    ]);

    $group->users()->attach([$host->id, $member->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 3,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    DB::table('official_record_sheets')->insert([
        'group_id' => $group->id,
        'date' => $date,
        'sheet_no' => 2,
        'scoring_mode' => 'hit_miss',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&month=2026-07&sheet_no=2");

    $response->assertOk();
    $response->assertSee(
        "/group/{$group->id}/lineup?date={$date}&month=2026-07&sheet_no=2",
        false
    );
});

it('opens past official record dates on the first sheet unless a sheet is selected', function () {
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-07-26 12:00:00'));

    try {
        $date = '2026-07-25';
        $host = User::factory()->create([
            'username' => 'host-past-official-first-sheet',
            'is_admin' => true,
        ]);
        $member = User::factory()->create([
            'username' => 'member-past-official-first-sheet',
            'is_admin' => false,
        ]);
        $group = Group::create([
            'name' => 'Past Official First Sheet Group',
            'host_user_id' => $host->id,
            'invite_code' => '9135',
        ]);
        $group->users()->attach([$host->id, $member->id]);

        $lineup = Lineup::create([
            'group_id' => $group->id,
            'date' => $date,
            'tate_size' => 1,
        ]);
        LineupMember::create([
            'lineup_id' => $lineup->id,
            'user_id' => $member->id,
            'position' => 1,
            'is_absent' => false,
            'is_late' => false,
        ]);

        DB::table('official_record_sheets')->insert([
            [
                'group_id' => $group->id,
                'date' => $date,
                'sheet_no' => 1,
                'scoring_mode' => 'hit_miss',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_id' => $group->id,
                'date' => $date,
                'sheet_no' => 2,
                'scoring_mode' => 'hit_miss',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($host)
            ->get("/group/{$group->id}/records?date={$date}")
            ->assertOk()
            ->assertSee('<strong>1ページ目</strong>', false);

        $this->actingAs($host)
            ->get("/group/{$group->id}/records?date={$date}&sheet_no=2")
            ->assertOk()
            ->assertSee('<strong>2ページ目</strong>', false);
    } finally {
        \Carbon\Carbon::setTestNow();
    }
});

it('shows the first group official shot at the bottom of the vertical record column', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-official-shot-order',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'member-official-shot-order',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Official Shot Order Group',
        'host_user_id' => $host->id,
        'invite_code' => '7531',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 1,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    $record = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 1,
        'lineup_tate_size' => 1,
    ]);
    $firstShot = Shot::create([
        'record_id' => $record->id,
        'shot_no' => 1,
    ]);
    $fourthShot = Shot::create([
        'record_id' => $record->id,
        'shot_no' => 4,
    ]);

    $content = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}")
        ->assertOk()
        ->getContent();

    expect(strpos($content, 'data-id="' . $fourthShot->id . '"'))
        ->toBeLessThan(strpos($content, 'data-id="' . $firstShot->id . '"'));
});

it('converts saved group official shots to the bottom-up order without touching standalone official records', function () {
    $date = '2026-07-26';
    $host = User::factory()->create([
        'username' => 'host-official-shot-convert',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'member-official-shot-convert',
        'is_admin' => false,
    ]);
    $standaloneUser = User::factory()->create([
        'username' => 'standalone-official-shot-convert',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Official Shot Convert Group',
        'host_user_id' => $host->id,
        'invite_code' => '8642',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 1,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $groupRecord = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 1,
        'lineup_tate_size' => 1,
    ]);
    $groupShots = collect(range(1, 4))->mapWithKeys(fn($shotNo) => [
        $shotNo => Shot::create([
            'record_id' => $groupRecord->id,
            'shot_no' => $shotNo,
        ]),
    ]);

    $standaloneRecord = Record::create([
        'user_id' => $standaloneUser->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
    ]);
    $standaloneFirstShot = Shot::create([
        'record_id' => $standaloneRecord->id,
        'shot_no' => 1,
    ]);

    $migration = include database_path('migrations/2026_08_01_000001_reverse_group_official_shot_order.php');
    $migration->up();

    expect($groupShots[1]->fresh()->shot_no)->toBe(4);
    expect($groupShots[2]->fresh()->shot_no)->toBe(3);
    expect($groupShots[3]->fresh()->shot_no)->toBe(2);
    expect($groupShots[4]->fresh()->shot_no)->toBe(1);
    expect($standaloneFirstShot->fresh()->shot_no)->toBe(1);
});

it('shows the first match shot at the bottom of the vertical record column', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-match-shot-order',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'member-match-shot-order',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Match Shot Order Group',
        'host_user_id' => $host->id,
        'invite_code' => '9753',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $member->id,
        'tate_no' => 1,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    $record = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'match',
        'official_sheet_no' => 1,
        'match_team_id' => $team->id,
    ]);
    $firstShot = Shot::create([
        'record_id' => $record->id,
        'shot_no' => 1,
    ]);
    $fourthShot = Shot::create([
        'record_id' => $record->id,
        'shot_no' => 4,
    ]);

    $content = $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}&team_id={$team->id}")
        ->assertOk()
        ->getContent();

    expect(strpos($content, 'data-id="' . $fourthShot->id . '"'))
        ->toBeLessThan(strpos($content, 'data-id="' . $firstShot->id . '"'));
});

it('keeps late match members in the match records while excluding absent members', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-late-match-record',
        'is_admin' => true,
    ]);
    $lateMember = User::factory()->create([
        'name' => 'Late Match Member',
        'username' => 'late-match-record',
        'is_admin' => false,
    ]);
    $absentMember = User::factory()->create([
        'name' => 'Absent Match Member',
        'username' => 'absent-match-record',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Late Match Record Group',
        'host_user_id' => $host->id,
        'invite_code' => '2468',
    ]);
    $group->users()->attach([$host->id, $lateMember->id, $absentMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 2,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $lateMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => true,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $absentMember->id,
        'position' => 2,
        'is_absent' => true,
        'is_late' => false,
    ]);

    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 2,
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $lateMember->id,
        'tate_no' => 1,
        'position' => 1,
        'is_absent' => false,
        'is_late' => true,
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $absentMember->id,
        'tate_no' => 1,
        'position' => 2,
        'is_absent' => true,
        'is_late' => false,
    ]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}&team_id={$team->id}")
        ->assertOk();

    $lateRecord = Record::where('user_id', $lateMember->id)
        ->where('date', $date)
        ->where('practice_type', 'match')
        ->where('match_team_id', $team->id)
        ->where('tate_no', 1)
        ->first();

    expect($lateRecord)->not->toBeNull();
    expect($lateRecord->shots()->count())->toBe(4);
    expect(Record::where('user_id', $absentMember->id)
        ->where('date', $date)
        ->where('practice_type', 'match')
        ->where('match_team_id', $team->id)
        ->exists())->toBeFalse();
});

it('creates match records for late members when saving a match lineup', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-save-late-match-record',
        'is_admin' => true,
    ]);
    $lateMember = User::factory()->create([
        'username' => 'save-late-match-record',
        'is_admin' => false,
    ]);
    $absentMember = User::factory()->create([
        'username' => 'save-absent-match-record',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Save Late Match Record Group',
        'host_user_id' => $host->id,
        'invite_code' => '8640',
    ]);
    $group->users()->attach([$host->id, $lateMember->id, $absentMember->id]);

    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 2,
    ]);

    $this->actingAs($host)
        ->postJson("/match-teams/{$team->id}/tate", [
            'date' => $date,
            'tate_no' => 1,
            'members' => [
                [
                    'user_id' => $lateMember->id,
                    'position' => 1,
                    'late' => true,
                    'absent' => false,
                ],
                [
                    'user_id' => $absentMember->id,
                    'position' => 2,
                    'late' => false,
                    'absent' => true,
                ],
            ],
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $lateRecord = Record::where('user_id', $lateMember->id)
        ->where('date', $date)
        ->where('practice_type', 'match')
        ->where('match_team_id', $team->id)
        ->where('tate_no', 1)
        ->first();

    expect($lateRecord)->not->toBeNull();
    expect($lateRecord->shots()->count())->toBe(4);
    expect(Record::where('user_id', $absentMember->id)
        ->where('date', $date)
        ->where('practice_type', 'match')
        ->where('match_team_id', $team->id)
        ->exists())->toBeFalse();
});

it('converts saved match shots to the bottom-up order', function () {
    $date = '2026-07-26';
    $host = User::factory()->create([
        'username' => 'host-match-shot-convert',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'member-match-shot-convert',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Match Shot Convert Group',
        'host_user_id' => $host->id,
        'invite_code' => '0864',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);
    $matchRecord = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'match',
        'official_sheet_no' => 1,
        'match_team_id' => $team->id,
    ]);
    $matchShots = collect(range(1, 4))->mapWithKeys(fn($shotNo) => [
        $shotNo => Shot::create([
            'record_id' => $matchRecord->id,
            'shot_no' => $shotNo,
        ]),
    ]);

    $standaloneMatchRecord = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 2,
        'practice_type' => 'match',
        'official_sheet_no' => 1,
    ]);
    $standaloneFirstShot = Shot::create([
        'record_id' => $standaloneMatchRecord->id,
        'shot_no' => 1,
    ]);

    $migration = include database_path('migrations/2026_08_01_000002_reverse_group_match_shot_order.php');
    $migration->up();

    expect($matchShots[1]->fresh()->shot_no)->toBe(4);
    expect($matchShots[2]->fresh()->shot_no)->toBe(3);
    expect($matchShots[3]->fresh()->shot_no)->toBe(2);
    expect($matchShots[4]->fresh()->shot_no)->toBe(1);
    expect($standaloneFirstShot->fresh()->shot_no)->toBe(1);
});

it('keeps the selected match team color on official record assignment frames', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-match-team-color',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'member-match-team-color',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Match Team Color Group',
        'host_user_id' => $host->id,
        'invite_code' => '1357',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 1,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $deletedTeam = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '削除済みチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);
    $deletedTeam->delete();

    $targetTeam = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '日本インカレ男子',
        'division' => 'male',
        'tate_size' => 1,
    ]);
    $femaleTeam = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '日本インカレ女子',
        'division' => 'female',
        'tate_size' => 1,
    ]);
    $mixedTeam = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '混合チーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);
    $record = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 1,
        'lineup_tate_size' => 1,
    ]);
    MatchTeamMember::create([
        'match_team_id' => $targetTeam->id,
        'date' => $date,
        'user_id' => $member->id,
        'tate_no' => 1,
        'position' => 1,
        'official_record_id' => $record->id,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $matchContent = $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}&team_id={$targetTeam->id}")
        ->assertOk()
        ->getContent();
    expect($matchContent)->toContain("id=\"match-team-{$targetTeam->id}\" style=\"--match-team-color: #0d6efd;\"");
    expect($matchContent)->toContain("id=\"match-team-{$femaleTeam->id}\" style=\"--match-team-color: #dc3545;\"");
    expect($matchContent)->toContain("id=\"match-team-{$mixedTeam->id}\" style=\"--match-team-color: #198754;\"");

    $officialContent = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}")
        ->assertOk()
        ->getContent();
    expect(substr_count($officialContent, '--latest-match-color: #0d6efd;'))->toBeGreaterThanOrEqual(2);

    $editContent = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&match_team_id={$targetTeam->id}&match_tate_no=1&match_position=1&return_to=official")
        ->assertOk()
        ->getContent();
    expect($editContent)->toContain('--match-selection-color: #0d6efd;');
});

it('fills the next official sheet with one page from the first unentered tate', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-official-partial-sheet',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'member-official-partial-sheet',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '1122',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 1,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    foreach (range(1, 5) as $tateNo) {
        $record = Record::create([
            'user_id' => $member->id,
            'date' => $date,
            'tate_no' => $tateNo,
            'practice_type' => 'official',
            'official_sheet_no' => 1,
            'lineup_position' => 1,
            'lineup_tate_size' => 1,
        ]);

        if ($tateNo <= 4) {
            Shot::create([
                'record_id' => $record->id,
                'shot_no' => 1,
                'result' => 'hit',
            ]);
        }
    }

    $response = $this->actingAs($host)
        ->post("/group/{$group->id}/records/switch-sheet", [
            'date' => $date,
            'sheet_no' => 1,
        ]);

    $response->assertRedirect("/group/{$group->id}/records?date={$date}&sheet_no=2");
    expect(Record::where('user_id', $member->id)
        ->where('date', $date)
        ->where('practice_type', 'official')
        ->where('official_sheet_no', 1)
        ->pluck('tate_no')
        ->sort()
        ->values()
        ->all())->toBe([1, 2, 3, 4]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&sheet_no=2")
        ->assertOk();

    expect(Record::where('user_id', $member->id)
        ->where('date', $date)
        ->where('practice_type', 'official')
        ->where('official_sheet_no', 2)
        ->pluck('tate_no')
        ->unique()
        ->sort()
        ->values()
        ->all())->toBe([5, 6, 7, 8, 9]);
});

it('shows latest match tate assignments only on the selected official record column', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-match-marker',
        'is_admin' => true,
    ]);
    $olderMember = User::factory()->create([
        'name' => 'Older Member',
        'username' => 'older-match-marker',
        'is_admin' => false,
    ]);
    $latestMember = User::factory()->create([
        'name' => 'Latest Member',
        'username' => 'latest-match-marker',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '3456',
    ]);

    $group->users()->attach([$host->id, $olderMember->id, $latestMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 2,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $olderMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $latestMember->id,
        'position' => 2,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $selectedRecord = null;

    foreach (range(1, 5) as $tateNo) {
        $record = Record::create([
            'user_id' => $latestMember->id,
            'date' => $date,
            'tate_no' => $tateNo,
            'practice_type' => 'official',
            'official_sheet_no' => 1,
            'lineup_position' => 2,
            'lineup_tate_size' => 2,
        ]);

        if ($tateNo === 3) {
            $selectedRecord = $record;
        }
    }

    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 2,
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $olderMember->id,
        'tate_no' => 1,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $latestMember->id,
        'tate_no' => 2,
        'position' => 2,
        'official_record_id' => $selectedRecord->id,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}");

    $response->assertOk();
    $response->assertSee('official-match-assigned', false);
    $response->assertSee('official-match-frame-label', false);
    $response->assertSee('Aチーム 2立目 落', false);
    $response->assertDontSee('Aチーム 1立目 大前', false);
    expect(substr_count($response->getContent(), 'official-match-assigned'))->toBe(1);
});

it('shows match team timer and add tate controls on the official record screen', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-official-match-controls',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'member-official-match-controls',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '4567',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 1,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => '2026-07-20',
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);
    MatchTeam::create([
        'group_id' => $group->id,
        'date' => '2026-07-20',
        'name' => 'Bチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);
    $record = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 1,
        'lineup_tate_size' => 1,
    ]);
    Shot::create([
        'record_id' => $record->id,
        'shot_no' => 1,
        'result' => 'hit',
    ]);
    Shot::create([
        'record_id' => $record->id,
        'shot_no' => 2,
        'result' => 'miss',
    ]);
    Shot::create([
        'record_id' => $record->id,
        'shot_no' => 3,
        'result' => 'hit',
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $member->id,
        'tate_no' => 1,
        'position' => 1,
        'official_record_id' => $record->id,
        'is_absent' => false,
        'is_late' => false,
    ]);
    MatchTateMeta::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'tate_no' => 1,
        'elapsed_seconds' => 90,
        'is_timer_running' => false,
        'timer_started_at' => null,
        'scoring_mode' => 'hit_miss',
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}");

    $response->assertOk();
    $response->assertSee('official-match-team-controls', false);
    $response->assertSee('試合操作', false);
    $response->assertSee('Aチーム', false);
    $response->assertSee('Bチーム', false);
    $response->assertSee('1立目', false);
    $response->assertSee('2中', false);
    $response->assertSee('official-match-team-hit-count', false);
    $response->assertSee('official-match-team-tate-label', false);
    $response->assertSee("data-official-match-team-hit-counter=\"{$team->id}-1\"", false);
    $response->assertSee("data-official-match-team-counters=\"{$team->id}-1\"", false);
    $response->assertSee("match_team_id={$team->id}&match_tate_no=1&match_position=1", false);
    $response->assertSee('return_to=official', false);
    $response->assertSee('編集', false);
    $response->assertSee('01:30', false);
    $response->assertSee('return_to', false);
    $response->assertSee('official', false);
    $response->assertSee("/group/{$group->id}/match-add-tate", false);

    $editResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&month=2026-07&sheet_no=1&match_team_id={$team->id}&match_tate_no=1&match_position=1&return_to=official");

    $editResponse->assertOk();
    $editResponse->assertSee('正規連記録へ戻る', false);
    $editResponse->assertSee("/group/{$group->id}/records?date={$date}&amp;month=2026-07&amp;sheet_no=1#official-match-team-controls", false);

    $responseWithTeamId = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&team_id={$team->id}");

    $responseWithTeamId->assertOk();
    $responseWithTeamId->assertSee('Aチーム', false);
    $responseWithTeamId->assertSee('Bチーム', false);
});

it('does not carry the selected match team when switching from match records to official records', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-match-switch-team',
        'is_admin' => true,
    ]);
    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '6789',
    ]);
    $group->users()->attach($host->id);
    MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);
    $selectedTeam = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Bチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}&team_id={$selectedTeam->id}");

    $response->assertOk();
    $response->assertSee("href=\"/group/{$group->id}/records?date={$date}&month=2026-07\"", false);
    $response->assertDontSee("href=\"/group/{$group->id}/records?date={$date}&month=2026-07&team_id={$selectedTeam->id}\"", false);
    $response->assertSee('style="--match-team-color:', false);
    $response->assertSee('match-team-color-dot', false);
});

it('returns to the official record screen after adding a match tate from the official controls', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-official-match-add',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'member-official-match-add',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '5678',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $firstRecord = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 1,
        'lineup_tate_size' => 1,
    ]);
    $secondRecord = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 2,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 1,
        'lineup_tate_size' => 1,
    ]);
    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $member->id,
        'tate_no' => 1,
        'position' => 1,
        'official_record_id' => $firstRecord->id,
        'is_absent' => false,
        'is_late' => false,
    ]);
    Shot::create([
        'record_id' => $firstRecord->id,
        'shot_no' => 1,
        'result' => 'hit',
    ]);

    $response = $this->actingAs($host)
        ->post("/group/{$group->id}/match-add-tate", [
            'date' => $date,
            'month' => '2026-07',
            'team_id' => $team->id,
            'sheet_no' => 1,
            'return_to' => 'official',
        ]);

    $response->assertRedirect("/group/{$group->id}/records?date={$date}&month=2026-07&sheet_no=1#official-match-team-controls");
    $this->assertDatabaseHas('match_team_members', [
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $member->id,
        'tate_no' => 2,
        'position' => 1,
        'official_record_id' => $secondRecord->id,
    ]);
});

it('does not add a match tate before the current team tate has any score entered', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-match-add-no-score',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'member-match-add-no-score',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '7890',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $firstRecord = Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 1,
        'lineup_tate_size' => 1,
    ]);
    Record::create([
        'user_id' => $member->id,
        'date' => $date,
        'tate_no' => 2,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 1,
        'lineup_tate_size' => 1,
    ]);
    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $member->id,
        'tate_no' => 1,
        'position' => 1,
        'official_record_id' => $firstRecord->id,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $response = $this->actingAs($host)
        ->post("/group/{$group->id}/match-add-tate", [
            'date' => $date,
            'month' => '2026-07',
            'team_id' => $team->id,
            'sheet_no' => 1,
            'return_to' => 'official',
        ]);

    $response->assertRedirect("/group/{$group->id}/records?date={$date}&month=2026-07&sheet_no=1#official-match-team-controls");
    $response->assertSessionHas('error', '1立目の的中を入力してから、＋立を押してください。');
    $response->assertSessionHas('error_alert', '1立目の的中を入力してから、＋立を押してください。');
    $this->assertDatabaseMissing('match_team_members', [
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $member->id,
        'tate_no' => 2,
    ]);
});

it('keeps entered official records for members who left the group while hiding them from lineup settings', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-former-official-record',
        'is_admin' => true,
    ]);
    $activeMember = User::factory()->create([
        'name' => 'Active Member',
        'username' => 'active-former-official-record',
        'is_admin' => false,
    ]);
    $formerMember = User::factory()->create([
        'name' => 'Former Member',
        'username' => 'former-official-record',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Former Record Group',
        'host_user_id' => $host->id,
        'invite_code' => '6420',
    ]);
    $group->users()->attach([$host->id, $activeMember->id, $formerMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 2,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $activeMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $formerMember->id,
        'position' => 2,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $record = Record::create([
        'user_id' => $formerMember->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 2,
        'lineup_tate_size' => 2,
    ]);
    Shot::create([
        'record_id' => $record->id,
        'shot_no' => 1,
        'result' => 'hit',
    ]);

    DB::table('group_user')
        ->where('group_id', $group->id)
        ->where('user_id', $formerMember->id)
        ->update(['deleted_at' => now()]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}")
        ->assertOk()
        ->assertSee('Former Member');

    $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}")
        ->assertOk()
        ->assertDontSee('Former Member');
});

it('hides entered official records for active members removed from the lineup', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-active-removed-record',
        'is_admin' => true,
    ]);
    $placedMember = User::factory()->create([
        'name' => 'Placed Active Member',
        'username' => 'placed-active-removed-record',
        'is_admin' => false,
    ]);
    $removedMember = User::factory()->create([
        'name' => 'Removed Active Member',
        'username' => 'removed-active-record',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Active Removed Record Group',
        'host_user_id' => $host->id,
        'invite_code' => '8641',
    ]);
    $group->users()->attach([$host->id, $placedMember->id, $removedMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 2,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $placedMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $removedMember->id,
        'position' => null,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $record = Record::create([
        'user_id' => $removedMember->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 2,
        'lineup_tate_size' => 2,
    ]);
    Shot::create([
        'record_id' => $record->id,
        'shot_no' => 1,
        'result' => 'hit',
    ]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}")
        ->assertOk()
        ->assertSee('Placed Active Member')
        ->assertDontSee('Removed Active Member');

    $this->assertDatabaseHas('records', [
        'id' => $record->id,
        'user_id' => $removedMember->id,
        'practice_type' => 'official',
    ]);
});

it('keeps saved previous sheet records for active members later removed from the current lineup', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-previous-sheet-removed',
        'is_admin' => true,
    ]);
    $placedMember = User::factory()->create([
        'name' => 'Still On Current Sheet',
        'username' => 'still-current-sheet',
        'is_admin' => false,
    ]);
    $removedMember = User::factory()->create([
        'name' => 'Removed After Sheet Switch',
        'username' => 'removed-after-sheet-switch',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Previous Sheet Snapshot Group',
        'host_user_id' => $host->id,
        'invite_code' => '9750',
    ]);
    $group->users()->attach([$host->id, $placedMember->id, $removedMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 2,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $placedMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $removedMember->id,
        'position' => null,
        'is_absent' => false,
        'is_late' => false,
    ]);

    foreach ([
        [$placedMember, 1],
        [$removedMember, 2],
    ] as [$member, $position]) {
        $record = Record::create([
            'user_id' => $member->id,
            'date' => $date,
            'tate_no' => 1,
            'practice_type' => 'official',
            'official_sheet_no' => 1,
            'lineup_position' => $position,
            'lineup_tate_size' => 2,
        ]);
        Shot::create([
            'record_id' => $record->id,
            'shot_no' => 1,
            'result' => 'hit',
        ]);
    }

    DB::table('official_record_sheets')->insert([
        [
            'group_id' => $group->id,
            'date' => $date,
            'sheet_no' => 1,
            'scoring_mode' => 'hit_miss',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'group_id' => $group->id,
            'date' => $date,
            'sheet_no' => 2,
            'scoring_mode' => 'hit_miss',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&sheet_no=1")
        ->assertOk()
        ->assertSee('Still On Current Sheet')
        ->assertSee('Removed After Sheet Switch');

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&sheet_no=2")
        ->assertOk()
        ->assertSee('Still On Current Sheet')
        ->assertDontSee('Removed After Sheet Switch');
});

it('shows soft-deleted group members only on official records when they have entered official scores', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-soft-deleted-member',
        'is_admin' => true,
    ]);
    $activeMember = User::factory()->create([
        'name' => 'Active Member',
        'username' => 'active-soft-deleted-member',
        'is_admin' => false,
    ]);
    $formerMember = User::factory()->create([
        'name' => 'Former Soft Deleted',
        'username' => 'former-soft-deleted-member',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Soft Deleted Member Group',
        'host_user_id' => $host->id,
        'invite_code' => '2460',
    ]);
    $group->users()->attach([$host->id, $activeMember->id, $formerMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 2,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $activeMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $record = Record::create([
        'user_id' => $formerMember->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 2,
        'lineup_tate_size' => 2,
    ]);
    Shot::create([
        'record_id' => $record->id,
        'shot_no' => 1,
        'result' => 'hit',
    ]);

    DB::table('group_user')
        ->where('group_id', $group->id)
        ->where('user_id', $formerMember->id)
        ->update(['deleted_at' => now()]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}")
        ->assertOk()
        ->assertSee('Former Soft Deleted');

    $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}")
        ->assertOk()
        ->assertDontSee('Former Soft Deleted');

    $this->actingAs($host)
        ->get("/group/{$group->id}/match-lineup?date={$date}")
        ->assertOk()
        ->assertDontSee('Former Soft Deleted');

    $this->actingAs($host)
        ->get("/group/{$group->id}/history?period=today")
        ->assertOk()
        ->assertDontSee('Former Soft Deleted');
});

it('keeps former member slots on unentered official tates when compacting empty slots', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-former-unentered-tates',
        'is_admin' => true,
    ]);
    $activeMember = User::factory()->create([
        'name' => 'Active For All Tates',
        'username' => 'active-former-unentered-tates',
        'is_admin' => false,
    ]);
    $formerMember = User::factory()->create([
        'name' => 'Former Sparse Tates',
        'username' => 'former-sparse-tates',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Former Unentered Tate Group',
        'host_user_id' => $host->id,
        'invite_code' => '7531',
    ]);
    $group->users()->attach([$host->id, $activeMember->id, $formerMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 2,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $activeMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $formerMember->id,
        'position' => 2,
        'is_absent' => false,
        'is_late' => false,
    ]);

    foreach (range(1, 5) as $tateNo) {
        $activeRecord = Record::create([
            'user_id' => $activeMember->id,
            'date' => $date,
            'tate_no' => $tateNo,
            'practice_type' => 'official',
            'official_sheet_no' => 1,
            'lineup_position' => 1,
            'lineup_tate_size' => 2,
        ]);
        Shot::create([
            'record_id' => $activeRecord->id,
            'shot_no' => 1,
            'result' => 'hit',
        ]);
    }

    foreach ([1, 2] as $tateNo) {
        $formerRecord = Record::create([
            'user_id' => $formerMember->id,
            'date' => $date,
            'tate_no' => $tateNo,
            'practice_type' => 'official',
            'official_sheet_no' => 1,
            'lineup_position' => 2,
            'lineup_tate_size' => 2,
        ]);
        Shot::create([
            'record_id' => $formerRecord->id,
            'shot_no' => 1,
            'result' => 'hit',
        ]);
    }

    DB::table('group_user')
        ->where('group_id', $group->id)
        ->where('user_id', $formerMember->id)
        ->update(['deleted_at' => now()]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}&compact_empty_slots=1");

    $response->assertOk();
    expect(substr_count($response->getContent(), 'data-user="' . $formerMember->id . '"'))->toBe(20);
});

it('keeps former member official records visible when no active lineup member remains', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-only-former-member-record',
        'is_admin' => true,
    ]);
    $formerMember = User::factory()->create([
        'name' => 'Only Former Member',
        'username' => 'only-former-member-record',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Only Former Group',
        'host_user_id' => $host->id,
        'invite_code' => '8640',
    ]);
    $group->users()->attach([$host->id, $formerMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 1,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $formerMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $record = Record::create([
        'user_id' => $formerMember->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
        'lineup_position' => 1,
        'lineup_tate_size' => 1,
    ]);
    Shot::create([
        'record_id' => $record->id,
        'shot_no' => 1,
        'result' => 'hit',
    ]);

    DB::table('group_user')
        ->where('group_id', $group->id)
        ->where('user_id', $formerMember->id)
        ->update(['deleted_at' => now()]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}")
        ->assertOk()
        ->assertSee('Only Former Member')
        ->assertDontSee('この日はまだ立順が設定されていません。');
});
