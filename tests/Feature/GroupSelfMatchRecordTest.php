<?php

use App\Models\Group;
use App\Models\MatchTeam;
use App\Models\MatchTeamMember;
use App\Models\Record;
use App\Models\User;

it('excludes self practice teams from official match controls and selection', function () {
    $date = '2026-09-24';
    $host = User::factory()->create(['is_admin' => true]);
    $group = Group::create([
        'name' => 'Separate Match Controls',
        'host_user_id' => $host->id,
        'invite_code' => '9726',
    ]);
    $group->users()->attach($host->id);
    $selfTeam = MatchTeam::create([
        'group_id' => $group->id,
        'record_scope' => 'self',
        'date' => $date,
        'name' => '自主練専用チーム',
        'division' => 'mixed',
        'tate_size' => 3,
    ]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}")
        ->assertOk()
        ->assertViewHas('officialMatchTeamControls', fn ($controls) => $controls->isEmpty())
        ->assertDontSee('自主練専用チーム');

    $officialTeam = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '正規連専用チーム',
        'division' => 'mixed',
        'tate_size' => 3,
    ]);

    $this->get("/group/{$group->id}/records?date={$date}&match_team_id={$selfTeam->id}&match_tate_no=1&match_position=1")
        ->assertOk()
        ->assertViewHas('officialMatchTeamControls', fn ($controls) => $controls->pluck('team_id')->all() === [$officialTeam->id])
        ->assertViewHas('matchSelection', fn ($selection) => $selection === null)
        ->assertSee('正規連専用チーム')
        ->assertDontSee('自主練専用チーム');

    $this->get("/group/{$group->id}/self-match-records?date={$date}")
        ->assertOk()
        ->assertSee('自主練専用チーム')
        ->assertDontSee('正規連専用チーム');
});

it('keeps self practice match teams separate and creates a self practice tate for each selected member', function () {
    $date = '2026-09-24';
    $host = User::factory()->create([
        'username' => 'self-match-host',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'self-match-member',
        'is_admin' => false,
        'gender' => 'male',
    ]);
    $group = Group::create([
        'name' => 'Self Match Group',
        'host_user_id' => $host->id,
        'invite_code' => '9724',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $this->actingAs($host)
        ->post("/group/{$group->id}/match-teams", [
            'date' => $date,
            'name' => '自主練A',
            'division' => 'mixed',
            'record_scope' => 'self',
            'tate_size' => 2,
        ])
        ->assertRedirect("/group/{$group->id}/self-match-records?date={$date}&team_id=1");

    $team = MatchTeam::where('group_id', $group->id)->firstOrFail();

    expect($team->record_scope)->toBe('self');

    $this->actingAs($host)
        ->postJson("/match-teams/{$team->id}/tate", [
            'date' => $date,
            'tate_no' => 1,
            'tate_size' => 2,
            'members' => [[
                'user_id' => $member->id,
                'position' => 1,
                'absent' => false,
                'late' => false,
            ]],
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $linkedRecordId = MatchTeamMember::where('match_team_id', $team->id)
        ->where('user_id', $member->id)
        ->value('official_record_id');
    $record = Record::findOrFail($linkedRecordId);

    expect($record->practice_type)->toBe('self')
        ->and($record->tate_no)->toBe(1)
        ->and($record->shots)->toHaveCount(4);

    $this->actingAs($host)
        ->postJson("/group/shot/{$record->shots->first()->id}", ['result' => 'hit'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($record->fresh()->shots->first()->result)->toBe('hit');

    $this->actingAs($host)
        ->deleteJson("/group/{$group->id}/self-records/{$record->id}")
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => '試合記録としてこの立は登録されているため削除できません。',
        ]);

    expect(Record::whereKey($record->id)->exists())->toBeTrue();

    $this->actingAs($host)
        ->get("/group/{$group->id}/self-match-records?date={$date}")
        ->assertOk()
        ->assertSee('自主練', false)
        ->assertSee('自主練A', false);

    $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}")
        ->assertOk()
        ->assertDontSee('自主練A', false);
});
