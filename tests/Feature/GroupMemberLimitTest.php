<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('shows a five character alphanumeric invite code field on the group join page', function () {
    $user = User::factory()->create([
        'name' => 'Join Form User',
        'username' => 'join-form-user',
        'is_admin' => false,
    ]);

    $this->actingAs($user)
        ->get('/groups/join')
        ->assertOk()
        ->assertSee('maxlength="5"', false)
        ->assertSee('pattern="[A-Za-z0-9]{5}"', false)
        ->assertSee('例：A1B2C')
        ->assertDontSee('maxlength="4"', false);
});

it('creates groups with five character alphanumeric invite codes', function () {
    $host = User::factory()->create([
        'name' => 'Generated Code Host',
        'username' => 'generated-code-host',
        'is_admin' => false,
    ]);

    $this->actingAs($host)
        ->post('/groups', [
            'name' => 'Generated Code Group',
        ])
        ->assertRedirect('/groups');

    $group = Group::where('host_user_id', $host->id)->firstOrFail();

    expect($group->invite_code)->toMatch('/^[A-Z0-9]{5}$/');
});

it('accepts lowercase alphanumeric group invite codes and rejects old four digit codes', function () {
    $host = User::factory()->create([
        'name' => 'Alpha Host',
        'username' => 'alpha-code-host',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Alpha Member',
        'username' => 'alpha-code-member',
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => 'Alpha Code Group',
        'host_user_id' => $host->id,
        'invite_code' => 'A1B2C',
    ]);
    $group->users()->attach($host->id);

    $this->actingAs($member)
        ->post('/groups/join', [
            'invite_code' => '1234',
        ])
        ->assertSessionHasErrors('invite_code');

    expect($member->fresh()->groups()->where('groups.id', $group->id)->exists())->toBeFalse();

    $this->actingAs($member)
        ->post('/groups/join', [
            'invite_code' => 'a1b2c',
        ])
        ->assertRedirect('/groups');

    expect($member->fresh()->groups()->where('groups.id', $group->id)->exists())->toBeTrue();
});

it('allows the system admin to update a group invite code and member limit', function () {
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
        ->get(route('admin.system.groups'))
        ->assertOk()
        ->assertSee('name="invite_code"', false)
        ->assertSee('招待コード');

    $this->actingAs($admin)
        ->patch(route('admin.system.groups.update', $group), [
            'invite_code' => 'x9z8y',
        ])
        ->assertRedirect();

    expect($group->fresh()->invite_code)->toBe('X9Z8Y');

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
        'invite_code' => 'A9751',
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
        'invite_code' => 'B1351',
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
