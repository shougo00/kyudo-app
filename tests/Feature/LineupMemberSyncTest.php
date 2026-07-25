<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('keeps one lineup member per active user when group membership rows are duplicated', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Member One',
        'username' => 'member',
        'is_admin' => false,
    ]);

    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '2468',
    ]);

    DB::table('group_user')->insert([
        [
            'group_id' => $group->id,
            'user_id' => $host->id,
            'deleted_at' => null,
        ],
        [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'deleted_at' => null,
        ],
        [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'deleted_at' => null,
        ],
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}");

    $response->assertOk();

    $lineup = Lineup::where('group_id', $group->id)
        ->where('date', $date)
        ->firstOrFail();
    $memberRows = LineupMember::where('lineup_id', $lineup->id)
        ->where('user_id', $member->id)
        ->get();

    expect($memberRows)->toHaveCount(1);
    expect(LineupMember::where('lineup_id', $lineup->id)->where('user_id', $host->id)->exists())->toBeFalse();

    $responseContent = $response->getContent();

    expect(substr_count($responseContent, 'data-id="' . $memberRows->first()->id . '"'))->toBe(1);

    $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}")
        ->assertOk();

    expect(LineupMember::where('lineup_id', $lineup->id)->where('user_id', $member->id)->count())->toBe(1);
});
