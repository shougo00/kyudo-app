<?php

use App\Models\Group;
use App\Models\RegistrationLicenseCode;
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

it('prevents a grouped license user from joining a different group', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'license-lock-host',
        'is_admin' => true,
    ]);
    $licensedGroup = Group::create([
        'name' => 'Licensed Group',
        'host_user_id' => $host->id,
        'invite_code' => 'L1111',
    ]);
    $otherGroup = Group::create([
        'name' => 'Other Group',
        'host_user_id' => $host->id,
        'invite_code' => 'O2222',
    ]);
    $licenseCode = RegistrationLicenseCode::create([
        'code' => 'GROUP-LOCK',
        'memo' => 'Group lock test',
        'group_id' => $licensedGroup->id,
        'is_active' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Locked Member',
        'username' => 'locked-member',
        'is_admin' => false,
        'registration_license_code_id' => $licenseCode->id,
    ]);

    $this->actingAs($member)
        ->post('/groups/join', [
            'invite_code' => $otherGroup->invite_code,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'このライセンスでは指定されたグループにのみ参加できます。');

    expect($member->fresh()->groups()->where('groups.id', $otherGroup->id)->exists())->toBeFalse();
});

it('allows a grouped license user to rejoin the licensed group', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'license-rejoin-host',
        'is_admin' => true,
    ]);
    $group = Group::create([
        'name' => 'Licensed Rejoin Group',
        'host_user_id' => $host->id,
        'invite_code' => 'R3333',
    ]);
    $licenseCode = RegistrationLicenseCode::create([
        'code' => 'GROUP-REJOIN',
        'memo' => 'Group rejoin test',
        'group_id' => $group->id,
        'is_active' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Licensed Former Member',
        'username' => 'licensed-former',
        'is_admin' => false,
        'registration_license_code_id' => $licenseCode->id,
    ]);

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

    expect($member->fresh()->groups()->where('groups.id', $group->id)->exists())->toBeTrue();
});

it('prevents a grouped license user from creating a new group', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'license-create-host',
        'is_admin' => true,
    ]);
    $group = Group::create([
        'name' => 'Licensed Create Guard Group',
        'host_user_id' => $host->id,
        'invite_code' => 'C4444',
    ]);
    $licenseCode = RegistrationLicenseCode::create([
        'code' => 'GROUP-CREATE-GUARD',
        'memo' => 'Group create guard test',
        'group_id' => $group->id,
        'is_active' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Create Guard Member',
        'username' => 'create-guard-member',
        'is_admin' => false,
        'registration_license_code_id' => $licenseCode->id,
    ]);

    $this->actingAs($member)
        ->post('/groups', [
            'name' => 'Blocked Created Group',
        ])
        ->assertRedirect('/groups')
        ->assertSessionHas('error', 'このライセンスでは指定されたグループにのみ参加できます。');

    expect(Group::where('name', 'Blocked Created Group')->exists())->toBeFalse();
});
