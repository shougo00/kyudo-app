<?php

use App\Models\Group;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;

it('orders monthly group records by grade and gender', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'monthly-host',
        'is_admin' => true,
    ]);

    $fourthGradeMale = User::factory()->create([
        'name' => 'Fourth Grade Male',
        'username' => 'monthly-fourth-male',
        'is_admin' => false,
        'grade_level' => 4,
        'gender' => 'male',
    ]);

    $fourthGradeFemale = User::factory()->create([
        'name' => 'Fourth Grade Female',
        'username' => 'monthly-fourth-female',
        'is_admin' => false,
        'grade_level' => 4,
        'gender' => 'female',
    ]);

    $thirdGradeMale = User::factory()->create([
        'name' => 'Third Grade Male',
        'username' => 'monthly-third-male',
        'is_admin' => false,
        'grade_level' => 3,
        'gender' => 'male',
    ]);

    $thirdGradeFemale = User::factory()->create([
        'name' => 'Third Grade Female',
        'username' => 'monthly-third-female',
        'is_admin' => false,
        'grade_level' => 3,
        'gender' => 'female',
    ]);

    $secondGradeMale = User::factory()->create([
        'name' => 'Second Grade Male',
        'username' => 'monthly-second-male',
        'is_admin' => false,
        'grade_level' => 2,
        'gender' => 'male',
    ]);

    $secondGradeFemale = User::factory()->create([
        'name' => 'Second Grade Female',
        'username' => 'monthly-second-female',
        'is_admin' => false,
        'grade_level' => 2,
        'gender' => 'female',
    ]);

    $firstGradeMale = User::factory()->create([
        'name' => 'First Grade Male',
        'username' => 'monthly-first-male',
        'is_admin' => false,
        'grade_level' => 1,
        'gender' => 'male',
    ]);

    $firstGradeFemale = User::factory()->create([
        'name' => 'First Grade Female',
        'username' => 'monthly-first-female',
        'is_admin' => false,
        'grade_level' => 1,
        'gender' => 'female',
    ]);

    $noGrade = User::factory()->create([
        'name' => 'No Grade',
        'username' => 'monthly-no-grade',
        'is_admin' => false,
        'grade_level' => null,
        'gender' => 'male',
    ]);

    $group = Group::create([
        'name' => 'Monthly Grade Order Group',
        'host_user_id' => $host->id,
        'invite_code' => '8642',
        'uses_grades' => true,
        'grade_count' => 4,
    ]);

    $group->users()->attach([
        $host->id,
        $firstGradeFemale->id,
        $secondGradeFemale->id,
        $noGrade->id,
        $thirdGradeFemale->id,
        $fourthGradeFemale->id,
        $firstGradeMale->id,
        $secondGradeMale->id,
        $thirdGradeMale->id,
        $fourthGradeMale->id,
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=monthly&month=2026-07")
        ->assertOk()
        ->assertSeeInOrder([
            'Fourth Grade Male',
            'Fourth Grade Female',
            'Third Grade Male',
            'Third Grade Female',
            'Second Grade Male',
            'Second Grade Female',
            'First Grade Male',
            'First Grade Female',
            'No Grade',
        ]);

    $content = $response->getContent();

    expect($content)->toContain('CSV出力');
    expect(preg_match('/<a[^>]+monthly-csv[^>]*data-monthly-loading/s', $content))->toBe(0);
});

it('saves the monthly print rank setting with off as the default', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'monthly-rank-setting-host',
        'is_admin' => true,
    ]);

    $group = Group::create([
        'name' => 'Monthly Rank Setting Group',
        'host_user_id' => $host->id,
        'invite_code' => '5291',
    ]);
    $group->users()->attach($host->id);

    expect($group->fresh()->show_monthly_rank_on_print)->toBeFalse();

    $this->actingAs($host)
        ->withSession(["settings_unlocked_group_{$group->id}" => true])
        ->patch(route('settings.update'), [
            'official_tates_per_page' => 5,
            'grade_count' => 3,
            'show_monthly_rank_on_print' => '1',
        ])
        ->assertSessionHas('status', 'settings-updated');

    expect($group->fresh()->show_monthly_rank_on_print)->toBeTrue();
});

it('shows monthly print ranks by total hit rate only when enabled', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'monthly-rank-print-host',
        'is_admin' => true,
    ]);
    $lowRateMember = User::factory()->create([
        'name' => 'A Low Rate Member',
        'username' => 'monthly-rank-low',
        'is_admin' => false,
    ]);
    $highRateMember = User::factory()->create([
        'name' => 'B High Rate Member',
        'username' => 'monthly-rank-high',
        'is_admin' => false,
    ]);
    $sameLowRateMember = User::factory()->create([
        'name' => 'C Same Low Rate Member',
        'username' => 'monthly-rank-same-low',
        'is_admin' => false,
    ]);

    $group = Group::create([
        'name' => 'Monthly Rank Print Group',
        'host_user_id' => $host->id,
        'invite_code' => '6418',
    ]);
    $group->users()->attach([$host->id, $lowRateMember->id, $highRateMember->id, $sameLowRateMember->id]);

    $createRecord = function (User $user, array $results) {
        $record = Record::create([
            'user_id' => $user->id,
            'date' => '2026-07-05',
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

    $createRecord($lowRateMember, ['hit', 'miss', 'miss', 'miss']);
    $createRecord($highRateMember, ['hit', 'hit', 'hit', 'hit']);
    $createRecord($sameLowRateMember, ['hit', 'miss', 'miss', 'miss']);

    $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=monthly&month=2026-07")
        ->assertOk()
        ->assertDontSee('順位')
        ->assertDontSee('1位')
        ->assertDontSee('2位');

    $group->update(['show_monthly_rank_on_print' => true]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=monthly&month=2026-07")
        ->assertOk()
        ->assertSee('順位')
        ->assertSeeInOrder([
            'A Low Rate Member',
            '25%',
            '2位',
            'B High Rate Member',
            '100%',
            '1位',
            'C Same Low Rate Member',
            '25%',
            '2位',
        ]);

    $content = $response->getContent();

    expect(substr_count($content, '>2位<'))->toBe(2);
    expect($content)->not->toContain('3位');
});
