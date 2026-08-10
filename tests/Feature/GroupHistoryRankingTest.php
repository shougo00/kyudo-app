<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;

it('shows the 20 person and all member ranking limit options', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'ranking-limit-host',
        'is_admin' => true,
    ]);
    $members = User::factory()
        ->count(21)
        ->sequence(fn($sequence) => [
            'name' => 'Ranking Member ' . ($sequence->index + 1),
            'username' => 'ranking-limit-member-' . ($sequence->index + 1),
            'is_admin' => false,
            'gender' => 'male',
        ])
        ->create();
    $group = Group::create([
        'name' => 'Ranking Limit Group',
        'host_user_id' => $host->id,
        'invite_code' => '7284',
    ]);
    $group->users()->attach($members->pluck('id')->push($host->id)->all());

    $limitResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=ranking&limit=20")
        ->assertOk()
        ->assertSee('上位5人')
        ->assertSee('上位10人')
        ->assertSee('上位20人')
        ->assertSee('全員')
        ->assertSee('value="20" selected', false);

    $limitContent = $limitResponse->getContent();
    $allTabPosition = strpos($limitContent, 'data-ranking-gender-tab="all"');
    $maleTabPosition = strpos($limitContent, 'data-ranking-gender-tab="male"');
    $femaleTabPosition = strpos($limitContent, 'data-ranking-gender-tab="female"');

    expect($allTabPosition)->not->toBeFalse();
    expect($maleTabPosition)->not->toBeFalse();
    expect($femaleTabPosition)->not->toBeFalse();
    expect($allTabPosition)->toBeLessThan($maleTabPosition);
    expect($maleTabPosition)->toBeLessThan($femaleTabPosition);
    expect(preg_match('/name="ranking_gender"\s+value="all"/s', $limitContent))->toBe(1);
    expect(substr_count($limitContent, 'data-ranking-gender-panel="all"'))->toBe(1);
    preg_match('/<section data-ranking-gender-panel="all"[^>]*>(.*?)<\/section>/s', $limitContent, $limitAllPanel);
    expect(substr_count($limitAllPanel[1], 'class="rank-card"'))->toBe(20);

    $allResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=ranking&limit=all")
        ->assertOk()
        ->assertSee('value="all" selected', false)
        ->assertSee('limit=all', false);

    $allContent = $allResponse->getContent();
    preg_match('/<section data-ranking-gender-panel="all"[^>]*>(.*?)<\/section>/s', $allContent, $fullAllPanel);
    expect($allContent)->toContain('全体ランキング');
    expect(substr_count($fullAllPanel[1], 'class="rank-card"'))->toBe(21);
});

it('keeps ranking filters when opening member details and returning', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'ranking-return-host',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Ranking Return Member',
        'username' => 'ranking-return-member',
        'is_admin' => false,
        'gender' => 'male',
    ]);
    $group = Group::create([
        'name' => 'Ranking Return Group',
        'host_user_id' => $host->id,
        'invite_code' => '9172',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $historyResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=ranking&period=week&limit=5&ranking_gender=female&score_types[]=official&score_types[]=self");

    $historyResponse->assertOk();
    $historyResponse->assertSee('data-ranking-gender-tab="male"', false);
    $historyResponse->assertSee('data-ranking-gender-tab="female"', false);
    $historyResponse->assertSee('data-ranking-gender-tab="all"', false);
    $historyResponse->assertSee('data-ranking-gender-value', false);
    $historyResponse->assertSee('value="female"', false);
    $historyResponse->assertSee('return_view=ranking', false);
    $historyResponse->assertSee('return_period=week', false);
    $historyResponse->assertSee('return_limit=5', false);
    $historyResponse->assertSee('return_ranking_gender=female', false);
    $historyResponse->assertSee('return_score_types%5B0%5D=official', false);
    $historyResponse->assertSee('return_score_types%5B1%5D=self', false);

    $detailResponse = $this->actingAs($host)
        ->get("/dashboard?group_id={$group->id}&user_id={$member->id}&return_view=ranking&return_period=week&return_limit=5&return_ranking_gender=female&return_score_types[]=official&return_score_types[]=self");

    $detailResponse->assertOk();
    $detailResponse->assertSee('view=ranking', false);
    $detailResponse->assertSee('period=week', false);
    $detailResponse->assertSee('limit=5', false);
    $detailResponse->assertSee('ranking_gender=female', false);
    $detailResponse->assertSee('score_types%5B0%5D=official', false);
    $detailResponse->assertSee('score_types%5B1%5D=self', false);
});

it('shows a combined ranking when both genders are selected', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'ranking-combined-host',
        'is_admin' => true,
    ]);
    $male = User::factory()->create([
        'name' => 'Combined Male',
        'username' => 'ranking-combined-male',
        'is_admin' => false,
        'gender' => 'male',
    ]);
    $female = User::factory()->create([
        'name' => 'Combined Female',
        'username' => 'ranking-combined-female',
        'is_admin' => false,
        'gender' => 'female',
    ]);
    $group = Group::create([
        'name' => 'Ranking Combined Group',
        'host_user_id' => $host->id,
        'invite_code' => '3764',
    ]);
    $group->users()->attach([$host->id, $male->id, $female->id]);

    $createRecord = function (User $user, array $results) {
        $record = Record::create([
            'user_id' => $user->id,
            'date' => now()->format('Y-m-d'),
            'tate_no' => 1,
            'practice_type' => 'official',
        ]);

        foreach (array_values($results) as $index => $result) {
            Shot::create([
                'record_id' => $record->id,
                'shot_no' => $index + 1,
                'result' => $result,
            ]);
        }
    };

    $createRecord($male, ['hit', 'hit', 'miss', 'miss']);
    $createRecord($female, ['hit', 'hit', 'hit', 'hit']);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=ranking&ranking_gender=all&score_types[]=official")
        ->assertOk()
        ->assertSee('全体ランキング')
        ->assertSee('data-ranking-gender-panel="male" hidden', false)
        ->assertSee('data-ranking-gender-panel="female" hidden', false);

    $response->assertSeeInOrder([
        'Combined Female',
        'Combined Male',
    ]);
});

it('filters rankings from calendar single-day and range selections', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'ranking-date-host',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Date Range Member',
        'username' => 'ranking-date-member',
        'is_admin' => false,
        'gender' => 'male',
    ]);
    $group = Group::create([
        'name' => 'Ranking Date Group',
        'host_user_id' => $host->id,
        'invite_code' => '4289',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $createRecord = function (string $date, array $results) use ($member) {
        $record = Record::create([
            'user_id' => $member->id,
            'date' => $date,
            'tate_no' => 1,
            'practice_type' => 'official',
        ]);

        foreach (array_values($results) as $index => $result) {
            Shot::create([
                'record_id' => $record->id,
                'shot_no' => $index + 1,
                'result' => $result,
            ]);
        }
    };

    $createRecord('2026-02-01', ['hit', 'hit', 'hit', 'hit']);
    $createRecord('2026-02-02', ['hit', 'hit', 'miss', 'miss']);
    $createRecord('2026-02-05', ['miss', 'miss', 'miss', 'miss']);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => '2026-02-02',
        'tate_size' => 3,
    ]);
    LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $rangeResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=ranking&period=custom&start_date=2026-02-01&end_date=2026-02-02&score_types[]=official")
        ->assertOk()
        ->assertSee('value="custom"', false)
        ->assertSee('data-ranking-preset="today"', false)
        ->assertSee('data-ranking-select-mode="range"', false)
        ->assertSee('data-ranking-loading-form', false)
        ->assertSee('data-ranking-calendar-toggle', false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('aria-label="カレンダーを開く"', false)
        ->assertSee('bi bi-calendar3', false)
        ->assertSee('calendar_open=1', false)
        ->assertSee('data-ranking-calendar-month-link', false)
        ->assertSee('data-calendar-month="2026-01"', false)
        ->assertDontSee('>日付指定<', false)
        ->assertDontSee('>範囲指定<', false)
        ->assertSee('value="2026-02-01"', false)
        ->assertSee('value="2026-02-02"', false)
        ->assertSee('2026年2月')
        ->assertSee('data-date="2026-02-02"', false)
        ->assertSee('立順あり')
        ->assertSee('8射')
        ->assertSee('6中')
        ->assertSee('75%')
        ->assertSee('return_start_date=2026-02-01', false)
        ->assertSee('return_end_date=2026-02-02', false)
        ->assertSee('return_calendar_month=2026-02', false);

    $singleDateResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=ranking&period=date&start_date=2026-02-02&score_types[]=official")
        ->assertOk()
        ->assertSee('value="date"', false)
        ->assertSee('data-ranking-select-mode="single"', false)
        ->assertSee('data-ranking-calendar-toggle', false)
        ->assertSee('value="2026-02-02"', false)
        ->assertSee('4射')
        ->assertSee('2中')
        ->assertSee('50%');

    $singleDateResponse->assertDontSee('8射');

    $pendingRangeResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=ranking&period=date&start_date=2026-02-01&calendar_month=2026-03&calendar_open=1&calendar_mode=range&range_anchor=2026-02-01&score_types[]=official")
        ->assertOk()
        ->assertSee('2026年3月')
        ->assertSee('name="calendar_mode"', false)
        ->assertSee('data-ranking-calendar-mode-value', false)
        ->assertSee('value="range"', false)
        ->assertSee('name="range_anchor"', false)
        ->assertSee('data-ranking-range-anchor', false)
        ->assertSee('value="2026-02-01"', false)
        ->assertSee('calendar_mode=range', false)
        ->assertSee('range_anchor=2026-02-01', false);

    $pendingRangeResponse->assertSee('data-calendar-month="2026-02"', false);

    $detailResponse = $this->actingAs($host)
        ->get("/dashboard?group_id={$group->id}&user_id={$member->id}&return_view=ranking&return_period=custom&return_start_date=2026-02-01&return_end_date=2026-02-02&return_calendar_month=2026-02&return_score_types[]=official");

    $detailResponse->assertOk();
    $detailResponse->assertSee('period=custom', false);
    $detailResponse->assertSee('start_date=2026-02-01', false);
    $detailResponse->assertSee('end_date=2026-02-02', false);
    $detailResponse->assertSee('calendar_month=2026-02', false);
    $detailResponse->assertSee('score_types%5B0%5D=official', false);
});
