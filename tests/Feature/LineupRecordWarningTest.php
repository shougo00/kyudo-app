<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
