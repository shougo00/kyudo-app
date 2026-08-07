<?php

use App\Models\Group;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;

function createSelfRecordWithTwoEnteredShots(User $user, string $date = '2026-08-08'): Record
{
    $record = Record::create([
        'user_id' => $user->id,
        'date' => $date,
        'tate_no' => 1,
        'practice_type' => 'self',
    ]);

    foreach ([1 => 'hit', 2 => 'miss', 3 => null, 4 => null] as $shotNo => $result) {
        Shot::create([
            'record_id' => $record->id,
            'shot_no' => $shotNo,
            'result' => $result,
        ]);
    }

    return $record;
}

it('shows entered shot count as the denominator on the personal self record page', function () {
    $user = User::factory()->create([
        'username' => 'personal-self-denominator',
        'is_admin' => false,
    ]);
    createSelfRecordWithTwoEnteredShots($user);

    $this->actingAs($user)
        ->get(route('home', [
            'date' => '2026-08-08',
            'type' => 'self',
        ]))
        ->assertOk()
        ->assertSee('<span class="hit-count">1/2</span>', false)
        ->assertDontSee('<span class="hit-count">1/4</span>', false);
});

it('shows entered shot count as the denominator on the group self record page', function () {
    $host = User::factory()->create([
        'username' => 'group-self-denominator-host',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => 'group-self-denominator-member',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Group Self Denominator',
        'host_user_id' => $host->id,
        'invite_code' => '6842',
    ]);
    $group->users()->attach([$host->id, $member->id]);
    createSelfRecordWithTwoEnteredShots($member);

    $this->actingAs($host)
        ->get(route('group.self-records', [
            'group' => $group->id,
            'date' => '2026-08-08',
            'user_id' => $member->id,
        ]))
        ->assertOk()
        ->assertSee('<span class="hit-count">1/2</span>', false)
        ->assertDontSee('<span class="hit-count">1/4</span>', false);
});
