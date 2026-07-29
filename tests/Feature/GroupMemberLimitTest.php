<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('allows the system admin to update a group member limit', function () {
    $admin = User::where('username', 'KANRI')->first()
        ?? User::factory()->create([
            'name' => 'System Admin',
            'username' => 'KANRI',
            'is_admin' => true,
        ]);
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'limit-host',
        'is_admin' => true,
    ]);
    $group = Group::create([
        'name' => 'Limited Group',
        'host_user_id' => $host->id,
        'invite_code' => '2469',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.system.groups.update', $group), [
            'max_members' => 12,
        ])
        ->assertRedirect();

    expect($group->fresh()->max_members)->toBe(12);
});

it('prevents joining a group that has reached its member limit', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'full-limit-host',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Current Member',
        'username' => 'full-limit-member',
        'is_admin' => false,
    ]);
    $newMember = User::factory()->create([
        'name' => 'New Member',
        'username' => 'full-limit-new',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Full Group',
        'host_user_id' => $host->id,
        'invite_code' => '9751',
        'max_members' => 2,
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $this->actingAs($newMember)
        ->post('/groups/join', [
            'invite_code' => $group->invite_code,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'このグループは最大人数（2人）に達しています');

    expect($newMember->fresh()->groups()->where('groups.id', $group->id)->exists())->toBeFalse();
});

it('does not count soft-deleted memberships toward the member limit', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'soft-limit-host',
        'is_admin' => true,
    ]);
    $formerMember = User::factory()->create([
        'name' => 'Former Member',
        'username' => 'soft-limit-former',
        'is_admin' => false,
    ]);
    $newMember = User::factory()->create([
        'name' => 'New Member',
        'username' => 'soft-limit-new',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Soft Limit Group',
        'host_user_id' => $host->id,
        'invite_code' => '1351',
        'max_members' => 2,
    ]);
    $group->users()->attach($host->id);
    DB::table('group_user')->insert([
        'group_id' => $group->id,
        'user_id' => $formerMember->id,
        'deleted_at' => now(),
    ]);

    $this->actingAs($newMember)
        ->post('/groups/join', [
            'invite_code' => $group->invite_code,
        ])
        ->assertRedirect('/groups');

    expect($newMember->fresh()->groups()->where('groups.id', $group->id)->exists())->toBeTrue();
});
