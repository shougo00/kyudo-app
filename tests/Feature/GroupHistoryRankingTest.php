<?php

use App\Models\Group;
use App\Models\User;

it('hides the 20 person and all member ranking limit options', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'ranking-limit-host',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Ranking Member',
        'username' => 'ranking-limit-member',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Ranking Limit Group',
        'host_user_id' => $host->id,
        'invite_code' => '7284',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=ranking&limit=all")
        ->assertOk()
        ->assertSee('上位5人')
        ->assertSee('上位10人')
        ->assertDontSee('上位20人')
        ->assertDontSee('全員')
        ->assertSee('limit=10', false);
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
