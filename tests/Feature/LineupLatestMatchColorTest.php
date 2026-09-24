<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\User;

it('ignores self match colors and position labels for unplaced official lineup members', function () {
    $date = '2026-09-24';
    $host = User::factory()->create(['is_admin' => true]);
    $selfOnlyMember = User::factory()->create(['is_admin' => false]);
    $sharedMember = User::factory()->create(['is_admin' => false]);
    $group = Group::create([
        'name' => 'Separate Lineup Colors',
        'host_user_id' => $host->id,
        'invite_code' => '8362',
    ]);
    $group->users()->attach([$host->id, $selfOnlyMember->id, $sharedMember->id]);
    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 3,
    ]);
    foreach ([$selfOnlyMember, $sharedMember] as $member) {
        LineupMember::create([
            'lineup_id' => $lineup->id,
            'user_id' => $member->id,
            'position' => null,
            'is_absent' => false,
            'is_late' => false,
        ]);
    }

    // Give the self team priority in sort order to expose cross-scope overrides.
    $selfTeam = MatchTeam::create([
        'group_id' => $group->id,
        'record_scope' => 'self',
        'date' => $date,
        'name' => 'Self Team',
        'division' => 'male',
        'color' => '#123456',
        'tate_size' => 3,
        'sort_order' => 1,
    ]);
    foreach ([$selfOnlyMember, $sharedMember] as $index => $member) {
        $selfTeam->members()->create([
            'date' => $date,
            'user_id' => $member->id,
            'tate_no' => 1,
            'position' => $index + 1,
            'is_absent' => false,
            'is_late' => false,
        ]);
    }

    $this->actingAs($host)->get("/group/{$group->id}/lineup?date={$date}")
        ->assertOk()
        ->assertViewHas('latestMatchUserColors', fn ($colors) => $colors->isEmpty())
        ->assertViewHas('latestMatchUserPositionLabels', fn ($labels) => $labels->isEmpty());

    MatchTeam::create([
        'group_id' => $group->id,
        'record_scope' => 'official',
        'date' => $date,
        'name' => 'Official Team',
        'division' => 'male',
        'color' => '#0d6efd',
        'tate_size' => 3,
        'sort_order' => 2,
    ])->members()->create([
        'date' => $date,
        'user_id' => $sharedMember->id,
        'tate_no' => 1,
        'position' => 3,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $this->get("/group/{$group->id}/lineup?date={$date}")
        ->assertOk()
        ->assertViewHas('latestMatchUserColors', fn ($colors) => $colors->all() === [$sharedMember->id => '#0d6efd'])
        ->assertViewHas('latestMatchUserPositionLabels', fn ($labels) => $labels->all() === [$sharedMember->id => '落']);
});

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
