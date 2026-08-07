<?php

use App\Models\Group;
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

    expect(substr_count($limitResponse->getContent(), 'class="rank-card"'))->toBe(20);

    $allResponse = $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=ranking&limit=all")
        ->assertOk()
        ->assertSee('value="all" selected', false)
        ->assertSee('limit=all', false);

    expect(substr_count($allResponse->getContent(), 'class="rank-card"'))->toBe(21);
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
        ->get("/group/{$group->id}/history?view=ranking&period=week&limit=5&score_types[]=official&score_types[]=self");

    $historyResponse->assertOk();
    $historyResponse->assertSee('return_view=ranking', false);
    $historyResponse->assertSee('return_period=week', false);
    $historyResponse->assertSee('return_limit=5', false);
    $historyResponse->assertSee('return_score_types%5B0%5D=official', false);
    $historyResponse->assertSee('return_score_types%5B1%5D=self', false);

    $detailResponse = $this->actingAs($host)
        ->get("/dashboard?group_id={$group->id}&user_id={$member->id}&return_view=ranking&return_period=week&return_limit=5&return_score_types[]=official&return_score_types[]=self");

    $detailResponse->assertOk();
    $detailResponse->assertSee('view=ranking', false);
    $detailResponse->assertSee('period=week', false);
    $detailResponse->assertSee('limit=5', false);
    $detailResponse->assertSee('score_types%5B0%5D=official', false);
    $detailResponse->assertSee('score_types%5B1%5D=self', false);
});
