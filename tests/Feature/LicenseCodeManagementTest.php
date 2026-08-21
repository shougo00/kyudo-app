<?php

use App\Models\Group;
use App\Models\RegistrationLicenseCode;
use App\Models\User;

it('lets KANRI manage registration license codes', function () {
    $admin = User::where('username', 'KANRI')->firstOrFail();
    $group = Group::create([
        'name' => 'License Group',
        'host_user_id' => $admin->id,
        'invite_code' => '3141',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.system.license-codes'))
        ->assertOk()
        ->assertSee('ライセンスコード管理')
        ->assertSee('紐づけグループ')
        ->assertSee('License Group');

    $this->actingAs($admin)
        ->post(route('admin.system.license-codes.store'), [
            'code' => 'join-2026',
            'memo' => '2026年度登録',
            'group_id' => $group->id,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $licenseCode = RegistrationLicenseCode::firstOrFail();

    expect($licenseCode->code)->toBe('JOIN-2026');
    expect($licenseCode->memo)->toBe('2026年度登録');
    expect($licenseCode->group_id)->toBe($group->id);
    expect($licenseCode->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.system.license-codes.update', $licenseCode), [
            'code' => 'join-2026-updated',
            'memo' => '停止中',
            'group_id' => '',
        ])
        ->assertRedirect();

    $licenseCode->refresh();
    expect($licenseCode->code)->toBe('JOIN-2026-UPDATED');
    expect($licenseCode->group_id)->toBeNull();
    expect($licenseCode->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('admin.system.license-codes.destroy', $licenseCode))
        ->assertRedirect();

    expect(RegistrationLicenseCode::count())->toBe(0);
});

it('blocks non KANRI users from the license code master', function () {
    $user = User::factory()->create([
        'username' => 'normal-user',
        'is_admin' => false,
    ]);

    $this->actingAs($user)
        ->get(route('admin.system.license-codes'))
        ->assertForbidden();
});
