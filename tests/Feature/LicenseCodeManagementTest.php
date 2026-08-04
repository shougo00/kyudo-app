<?php

use App\Models\RegistrationLicenseCode;
use App\Models\User;

it('lets KANRI manage registration license codes', function () {
    $admin = User::where('username', 'KANRI')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.system.license-codes'))
        ->assertOk()
        ->assertSee('ライセンスコード管理');

    $this->actingAs($admin)
        ->post(route('admin.system.license-codes.store'), [
            'code' => 'join-2026',
            'memo' => '2026年度登録',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $licenseCode = RegistrationLicenseCode::firstOrFail();

    expect($licenseCode->code)->toBe('JOIN-2026');
    expect($licenseCode->memo)->toBe('2026年度登録');
    expect($licenseCode->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.system.license-codes.update', $licenseCode), [
            'code' => 'join-2026-updated',
            'memo' => '停止中',
        ])
        ->assertRedirect();

    $licenseCode->refresh();
    expect($licenseCode->code)->toBe('JOIN-2026-UPDATED');
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
