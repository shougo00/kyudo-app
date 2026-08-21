<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('restores a soft-deleted group membership when a former member rejoins', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-restore-membership',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Former Member',
        'username' => 'former-restore-membership',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Restore Membership Group',
        'host_user_id' => $host->id,
        'invite_code' => 'C1350',
    ]);

    $group->users()->attach($host->id);
    DB::table('group_user')->insert([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'deleted_at' => now(),
    ]);

    $this->actingAs($member)
        ->post('/groups/join', [
            'invite_code' => $group->invite_code,
        ])
        ->assertRedirect('/groups');

    $membershipRows = DB::table('group_user')
        ->where('group_id', $group->id)
        ->where('user_id', $member->id)
        ->get();

    expect($membershipRows)->toHaveCount(1);
    expect($membershipRows->first()->deleted_at)->toBeNull();
    expect($member->fresh()->groups()->where('groups.id', $group->id)->exists())->toBeTrue();
});
