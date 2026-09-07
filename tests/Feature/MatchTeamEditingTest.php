<?php

use App\Models\Group;
use App\Models\MatchTateMeta;
use App\Models\MatchTeam;
use App\Models\MatchTeamMember;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;

it('assigns gradient default colors per division when match teams are created', function () {
    $date = '2026-09-07';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'match-team-gradient-host',
        'is_admin' => true,
    ]);
    $group = Group::create([
        'name' => 'Match Team Gradient Group',
        'host_user_id' => $host->id,
        'invite_code' => '9110',
    ]);
    $group->users()->attach($host->id);

    foreach ([
        ['男子A', 'male'],
        ['男子B', 'male'],
        ['女子A', 'female'],
        ['女子B', 'female'],
    ] as [$name, $division]) {
        $this->actingAs($host)
            ->post("/group/{$group->id}/match-teams", [
                'date' => $date,
                'name' => $name,
                'division' => $division,
                'tate_size' => 3,
            ])
            ->assertRedirect();
    }

    $maleA = MatchTeam::where('name', '男子A')->firstOrFail();
    $maleB = MatchTeam::where('name', '男子B')->firstOrFail();
    $femaleA = MatchTeam::where('name', '女子A')->firstOrFail();
    $femaleB = MatchTeam::where('name', '女子B')->firstOrFail();

    expect($maleA->color)->toBe('#0d6efd')
        ->and($maleB->color)->toBe('#0dcaf0')
        ->and($femaleA->color)->toBe('#dc3545')
        ->and($femaleB->color)->toBe('#e83e8c');

    $maleB->delete();

    $this->actingAs($host)
        ->post("/group/{$group->id}/match-teams", [
            'date' => $date,
            'name' => '男子C',
            'division' => 'male',
            'tate_size' => 3,
        ])
        ->assertRedirect();

    expect(MatchTeam::where('name', '男子C')->firstOrFail()->color)->toBe('#0dcaf0');

    $maleA->delete();

    $this->actingAs($host)
        ->post("/group/{$group->id}/match-teams", [
            'date' => $date,
            'name' => '男子D',
            'division' => 'male',
            'tate_size' => 3,
        ])
        ->assertRedirect();

    expect(MatchTeam::where('name', '男子D')->firstOrFail()->color)->toBe('#0d6efd');
});

it('refreshes legacy default match team colors into division gradients', function () {
    $date = '2026-09-07';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'match-team-legacy-gradient-host',
        'is_admin' => true,
    ]);
    $group = Group::create([
        'name' => 'Match Team Legacy Gradient Group',
        'host_user_id' => $host->id,
        'invite_code' => '9111',
    ]);
    $group->users()->attach($host->id);

    MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '旧男子A',
        'division' => 'male',
        'color' => '#0d6efd',
        'tate_size' => 3,
        'sort_order' => 1,
    ]);
    $legacyMaleB = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '旧男子B',
        'division' => 'male',
        'color' => '#0d6efd',
        'tate_size' => 3,
        'sort_order' => 2,
    ]);
    MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '旧女子A',
        'division' => 'female',
        'color' => '#dc3545',
        'tate_size' => 3,
        'sort_order' => 3,
    ]);
    $legacyFemaleB = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '旧女子B',
        'division' => 'female',
        'color' => '#dc3545',
        'tate_size' => 3,
        'sort_order' => 4,
    ]);
    $customMale = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '手動男子',
        'division' => 'male',
        'color' => '#123456',
        'tate_size' => 3,
        'sort_order' => 5,
    ]);

    $migration = include database_path('migrations/2026_09_07_000002_refresh_legacy_match_team_default_colors.php');
    $migration->up();

    expect($legacyMaleB->fresh()->color)->toBe('#0dcaf0')
        ->and($legacyFemaleB->fresh()->color)->toBe('#e83e8c')
        ->and($customMale->fresh()->color)->toBe('#123456');
});

it('updates active match teams and uses their saved order on the match records page', function () {
    $date = '2026-09-07';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'match-team-edit-host',
        'is_admin' => true,
    ]);
    $group = Group::create([
        'name' => 'Match Team Edit Group',
        'host_user_id' => $host->id,
        'invite_code' => '9107',
    ]);
    $group->users()->attach($host->id);

    $teamA = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'color' => '#198754',
        'tate_size' => 3,
        'sort_order' => 1,
    ]);
    $teamB = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Bチーム',
        'division' => 'mixed',
        'color' => '#0d6efd',
        'tate_size' => 2,
        'sort_order' => 2,
    ]);
    $disbandedTeam = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => '解散チーム',
        'division' => 'mixed',
        'color' => '#dc3545',
        'tate_size' => 1,
        'sort_order' => 3,
    ]);
    $disbandedTeam->delete();

    $initialPage = $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}");

    $initialPage->assertOk()
        ->assertSee('＋ チーム作成', false)
        ->assertSee('チーム編集', false)
        ->assertSee('class="match-team-edit-grip"', false)
        ->assertSee('title="長押しして並び替え"', false)
        ->assertDontSee('>三<', false)
        ->assertSee('Aチーム', false)
        ->assertSee('Bチーム', false)
        ->assertDontSee('解散チーム', false);

    $initialContent = $initialPage->getContent();
    $this->assertLessThan(
        strpos($initialContent, 'チーム編集'),
        strpos($initialContent, '＋ チーム作成')
    );

    $this->actingAs($host)
        ->patchJson("/group/{$group->id}/match-teams", [
            'teams' => [
                [
                    'id' => $teamB->id,
                    'name' => 'B改',
                    'tate_size' => 4,
                    'color' => '#112233',
                ],
                [
                    'id' => $teamA->id,
                    'name' => 'A改',
                    'tate_size' => 5,
                    'color' => '#445566',
                ],
            ],
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($teamB->fresh()->sort_order)->toBe(1)
        ->and($teamB->fresh()->name)->toBe('B改')
        ->and($teamB->fresh()->tate_size)->toBe(4)
        ->and($teamB->fresh()->color)->toBe('#112233')
        ->and($teamA->fresh()->sort_order)->toBe(2)
        ->and($teamA->fresh()->name)->toBe('A改')
        ->and($teamA->fresh()->tate_size)->toBe(5)
        ->and($teamA->fresh()->color)->toBe('#445566');

    $updatedPage = $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}");

    $updatedPage->assertOk()
        ->assertSee('style="--match-team-color: #112233;"', false)
        ->assertSee('style="--match-team-color: #445566;"', false);

    $updatedContent = $updatedPage->getContent();
    $this->assertLessThan(
        strpos($updatedContent, 'id="match-team-' . $teamA->id . '"'),
        strpos($updatedContent, 'id="match-team-' . $teamB->id . '"')
    );
});

it('keeps existing match tate slots when a team size is changed later', function () {
    $date = '2026-09-07';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'match-team-size-host',
        'is_admin' => true,
    ]);
    $members = User::factory()
        ->count(3)
        ->sequence(
            ['name' => 'First Archer', 'username' => 'match-team-size-first', 'is_admin' => false],
            ['name' => 'Second Archer', 'username' => 'match-team-size-second', 'is_admin' => false],
            ['name' => 'Third Archer', 'username' => 'match-team-size-third', 'is_admin' => false],
        )
        ->create();
    $group = Group::create([
        'name' => 'Match Team Size Group',
        'host_user_id' => $host->id,
        'invite_code' => '9108',
    ]);
    $group->users()->attach($members->pluck('id')->push($host->id)->all());
    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'color' => '#198754',
        'tate_size' => 3,
        'sort_order' => 1,
    ]);

    foreach ($members as $index => $member) {
        $position = $index + 1;

        MatchTeamMember::create([
            'match_team_id' => $team->id,
            'date' => $date,
            'user_id' => $member->id,
            'tate_no' => 1,
            'position' => $position,
            'is_absent' => false,
            'is_late' => false,
        ]);

        $record = Record::create([
            'user_id' => $member->id,
            'date' => $date,
            'tate_no' => 1,
            'practice_type' => 'match',
            'match_team_id' => $team->id,
            'official_sheet_no' => 1,
            'lineup_position' => $position,
            'lineup_tate_size' => 3,
        ]);

        foreach (range(1, 4) as $shotNo) {
            Shot::create([
                'record_id' => $record->id,
                'shot_no' => $shotNo,
                'result' => $shotNo === 1 ? 'hit' : null,
            ]);
        }
    }

    $this->actingAs($host)
        ->patchJson("/group/{$group->id}/match-teams", [
            'teams' => [
                [
                    'id' => $team->id,
                    'name' => 'Aチーム',
                    'tate_size' => 2,
                    'color' => '#198754',
                ],
            ],
        ])
        ->assertOk();

    expect($team->fresh()->tate_size)->toBe(2);
    expect((int) MatchTateMeta::where('match_team_id', $team->id)
        ->where('date', $date)
        ->where('tate_no', 1)
        ->value('tate_size'))->toBe(3);

    $page = $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}");

    $page->assertOk()
        ->assertSee('data-tate-size="3"', false)
        ->assertSee('Third Archer', false)
        ->assertSee('2人立', false);
});

it('applies team size changes to added tates that still have no entered score', function () {
    $date = '2026-09-07';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'match-team-open-tate-host',
        'is_admin' => true,
    ]);
    $members = User::factory()
        ->count(3)
        ->sequence(
            ['name' => 'Open First', 'username' => 'match-team-open-first', 'is_admin' => false],
            ['name' => 'Open Second', 'username' => 'match-team-open-second', 'is_admin' => false],
            ['name' => 'Open Third', 'username' => 'match-team-open-third', 'is_admin' => false],
        )
        ->create();
    $group = Group::create([
        'name' => 'Match Team Open Tate Group',
        'host_user_id' => $host->id,
        'invite_code' => '9109',
    ]);
    $group->users()->attach($members->pluck('id')->push($host->id)->all());
    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'color' => '#198754',
        'tate_size' => 3,
        'sort_order' => 1,
    ]);

    foreach ([1, 2] as $tateNo) {
        MatchTateMeta::create([
            'match_team_id' => $team->id,
            'date' => $date,
            'tate_no' => $tateNo,
            'tate_size' => 3,
            'scoring_mode' => 'hit_miss',
        ]);

        foreach ($members as $index => $member) {
            $position = $index + 1;

            MatchTeamMember::create([
                'match_team_id' => $team->id,
                'date' => $date,
                'user_id' => $member->id,
                'tate_no' => $tateNo,
                'position' => $position,
                'is_absent' => false,
                'is_late' => false,
            ]);

            $record = Record::create([
                'user_id' => $member->id,
                'date' => $date,
                'tate_no' => $tateNo,
                'practice_type' => 'match',
                'match_team_id' => $team->id,
                'official_sheet_no' => 1,
                'lineup_position' => $position,
                'lineup_tate_size' => 3,
            ]);

            foreach (range(1, 4) as $shotNo) {
                Shot::create([
                    'record_id' => $record->id,
                    'shot_no' => $shotNo,
                    'result' => $tateNo === 1 && $shotNo === 1 ? 'hit' : null,
                ]);
            }
        }
    }

    $this->actingAs($host)
        ->patchJson("/group/{$group->id}/match-teams", [
            'teams' => [
                [
                    'id' => $team->id,
                    'name' => 'Aチーム',
                    'tate_size' => 2,
                    'color' => '#198754',
                ],
            ],
        ])
        ->assertOk();

    expect((int) MatchTateMeta::where('match_team_id', $team->id)
        ->where('date', $date)
        ->where('tate_no', 1)
        ->value('tate_size'))->toBe(3);
    expect((int) MatchTateMeta::where('match_team_id', $team->id)
        ->where('date', $date)
        ->where('tate_no', 2)
        ->value('tate_size'))->toBe(2);
    expect(MatchTeamMember::where('match_team_id', $team->id)
        ->where('date', $date)
        ->where('tate_no', 2)
        ->where('user_id', $members[2]->id)
        ->exists())->toBeFalse();
    expect(Record::where('match_team_id', $team->id)
        ->where('date', $date)
        ->where('practice_type', 'match')
        ->where('tate_no', 2)
        ->where('user_id', $members[2]->id)
        ->exists())->toBeFalse();

    $page = $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}");

    $page->assertOk();
    $this->assertMatchesRegularExpression(
        '/data-team-id="' . $team->id . '"[\s\S]*?data-tate-no="2"[\s\S]*?data-tate-size="2"/',
        $page->getContent()
    );
});
