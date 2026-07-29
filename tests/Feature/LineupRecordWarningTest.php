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
