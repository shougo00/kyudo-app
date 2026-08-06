<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\User;

it('marks latest match members with their match team color and position label on the lineup page', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'username' => 'host-lineup-latest-match-color',
        'is_admin' => true,
    ]);
    $firstMember = User::factory()->create([
        'name' => 'First Match Member',
        'username' => 'first-lineup-latest-match-color',
        'is_admin' => false,
    ]);
    $secondMember = User::factory()->create([
        'name' => 'Second Match Member',
        'username' => 'second-lineup-latest-match-color',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Lineup Latest Match Color Group',
        'host_user_id' => $host->id,
        'invite_code' => '8361',
    ]);
    $group->users()->attach([$host->id, $firstMember->id, $secondMember->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 2,
    ]);
    $firstLineupMember = LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $firstMember->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    $secondLineupMember = LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $secondMember->id,
        'position' => 2,
        'is_absent' => false,
        'is_late' => false,
    ]);

    MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ])->members()->create([
        'date' => $date,
        'user_id' => $firstMember->id,
        'tate_no' => 1,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Bチーム',
        'division' => 'mixed',
        'tate_size' => 2,
    ])->members()->create([
        'date' => $date,
        'user_id' => $secondMember->id,
        'tate_no' => 1,
        'position' => 2,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}");

    $response->assertOk();
    $this->assertMatchesRegularExpression(
        '/data-id="' . $firstLineupMember->id . '"[\s\S]*?data-in-latest-match="1"[\s\S]*?data-latest-match-position-label="大前"/',
        $response->getContent()
    );
    $this->assertMatchesRegularExpression(
        '/data-id="' . $secondLineupMember->id . '"[\s\S]*?data-in-latest-match="1"[\s\S]*?data-latest-match-color="#198754"[\s\S]*?data-latest-match-position-label="落"/',
        $response->getContent()
    );
});
