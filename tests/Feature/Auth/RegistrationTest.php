<?php

use App\Models\RegistrationLicenseCode;

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
