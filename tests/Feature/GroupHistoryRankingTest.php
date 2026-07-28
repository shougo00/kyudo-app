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
