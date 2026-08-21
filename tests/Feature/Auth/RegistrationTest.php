<?php

use App\Models\Group;
use App\Models\RegistrationLicenseCode;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertSee('ライセンスコード');
});

test('new users can register', function () {
    $licenseCode = RegistrationLicenseCode::create([
        'code' => 'TEST-LICENSE',
        'memo' => 'Feature test',
        'is_active' => true,
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'password' => 'password',
        'password_confirmation' => 'password',
        'gender' => 'male',
        'license_code' => 'test-license',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
    $this->assertDatabaseHas('users', [
        'username' => 'testuser',
        'registration_license_code_id' => $licenseCode->id,
    ]);
});

test('new users join the linked group when registering with a grouped license code', function () {
    $host = User::factory()->create([
        'username' => 'license-group-host',
        'is_admin' => true,
    ]);
    $group = Group::create([
        'name' => 'Auto Join Group',
        'host_user_id' => $host->id,
        'invite_code' => '2718',
    ]);
    $licenseCode = RegistrationLicenseCode::create([
        'code' => 'GROUP-LICENSE',
        'memo' => 'Group auto join',
        'group_id' => $group->id,
        'is_active' => true,
    ]);

    $this->post('/register', [
        'name' => 'Group User',
        'username' => 'groupuser',
        'password' => 'password',
        'password_confirmation' => 'password',
        'gender' => 'female',
        'license_code' => 'group-license',
    ])->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'username' => 'groupuser',
        'registration_license_code_id' => $licenseCode->id,
    ]);

    $user = User::where('username', 'groupuser')->firstOrFail();

    $this->assertDatabaseHas('group_user', [
        'group_id' => $group->id,
        'user_id' => $user->id,
        'deleted_at' => null,
    ]);
    expect($user->fresh()->groups()->where('groups.id', $group->id)->exists())->toBeTrue();
});

test('new users can not register without an active license code', function () {
    RegistrationLicenseCode::create([
        'code' => 'INACTIVE-LICENSE',
        'memo' => 'Inactive test',
        'is_active' => false,
    ]);

    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'password' => 'password',
        'password_confirmation' => 'password',
        'gender' => 'male',
    ])->assertSessionHasErrors('license_code');

    $this->assertGuest();

    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'password' => 'password',
        'password_confirmation' => 'password',
        'gender' => 'male',
        'license_code' => 'INACTIVE-LICENSE',
    ])->assertSessionHasErrors('license_code');

    $this->assertGuest();
});

test('new users can not self-register as admins', function () {
    RegistrationLicenseCode::create([
        'code' => 'NORMAL-USER',
        'memo' => 'Normal user registration',
        'is_active' => true,
    ]);

    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'password' => 'password',
        'password_confirmation' => 'password',
        'gender' => 'male',
        'license_code' => 'NORMAL-USER',
        'is_admin' => '1',
    ])->assertRedirect(route('home', absolute: false));

    $this->assertDatabaseHas('users', [
        'username' => 'testuser',
        'is_admin' => false,
    ]);
});
