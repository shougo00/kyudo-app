<?php

use App\Models\Group;
use App\Models\RegistrationLicenseCode;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

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

it('lets KANRI download a user import template', function () {
    $admin = User::where('username', 'KANRI')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.system.users.import-template'))
        ->assertOk()
        ->assertDownload('user_import_template.csv');
});

it('imports users from CSV and joins the license group', function () {
    $admin = User::where('username', 'KANRI')->firstOrFail();
    $group = Group::create([
        'name' => 'CSV Import Group',
        'host_user_id' => $admin->id,
        'invite_code' => 'CSV01',
        'uses_grades' => true,
        'grade_count' => 4,
    ]);
    $licenseCode = RegistrationLicenseCode::create([
        'code' => 'CSV-GROUP',
        'memo' => 'CSV import',
        'is_active' => true,
        'group_id' => $group->id,
        'created_by' => $admin->id,
    ]);
    $csv = "\xEF\xBB\xBF表示名,ユーザー名,パスワード,性別,学年,ライセンスコード\n"
        . "山田太郎,yamada001,12345,男,2,CSV-GROUP\n"
        . "佐藤花子,sato002,password8,女性,,csv-group\n";

    $this->actingAs($admin)
        ->post(route('admin.system.users.import'), [
            'csv_file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', '2件のユーザーを取り込みました。');

    $yamada = User::where('username', 'yamada001')->firstOrFail();
    $sato = User::where('username', 'sato002')->firstOrFail();

    expect($yamada->name)->toBe('山田太郎');
    expect($yamada->registration_license_code_id)->toBe($licenseCode->id);
    expect($yamada->gender)->toBe('male');
    expect($yamada->grade_level)->toBe(2);
    expect($sato->gender)->toBe('female');
    expect($sato->grade_level)->toBeNull();

    expect(DB::table('group_user')
        ->where('group_id', $group->id)
        ->where('user_id', $yamada->id)
        ->whereNull('deleted_at')
        ->exists())->toBeTrue();
    expect(DB::table('group_user')
        ->where('group_id', $group->id)
        ->where('user_id', $sato->id)
        ->whereNull('deleted_at')
        ->exists())->toBeTrue();
});

it('imports users into a license group that does not use grade settings', function () {
    $admin = User::where('username', 'KANRI')->firstOrFail();
    $group = Group::create([
        'name' => 'No Grade CSV Group',
        'host_user_id' => $admin->id,
        'invite_code' => 'NG001',
        'uses_grades' => false,
        'grade_count' => 3,
    ]);
    $licenseCode = RegistrationLicenseCode::create([
        'code' => 'NO-GRADE',
        'memo' => 'No grade import',
        'is_active' => true,
        'group_id' => $group->id,
        'created_by' => $admin->id,
    ]);
    $csv = "\xEF\xBB\xBF表示名,ユーザー名,パスワード,性別,学年,ライセンスコード\n"
        . "学年なしユーザー,nograde001,password8,男,,NO-GRADE\n"
        . "学年ありユーザー,nograde002,password8,女,4,NO-GRADE\n";

    $this->actingAs($admin)
        ->post(route('admin.system.users.import'), [
            'csv_file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', '2件のユーザーを取り込みました。');

    $userWithoutGrade = User::where('username', 'nograde001')->firstOrFail();
    $userWithGrade = User::where('username', 'nograde002')->firstOrFail();

    expect($userWithoutGrade->registration_license_code_id)->toBe($licenseCode->id);
    expect($userWithoutGrade->grade_level)->toBeNull();
    expect($userWithGrade->registration_license_code_id)->toBe($licenseCode->id);
    expect($userWithGrade->grade_level)->toBe(4);
    expect(DB::table('group_user')
        ->where('group_id', $group->id)
        ->where('user_id', $userWithoutGrade->id)
        ->whereNull('deleted_at')
        ->exists())->toBeTrue();
    expect(DB::table('group_user')
        ->where('group_id', $group->id)
        ->where('user_id', $userWithGrade->id)
        ->whereNull('deleted_at')
        ->exists())->toBeTrue();
});

it('does not import any users when the CSV has validation errors', function () {
    $admin = User::where('username', 'KANRI')->firstOrFail();
    RegistrationLicenseCode::create([
        'code' => 'CSV-VALID',
        'memo' => 'CSV import',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $csv = "\xEF\xBB\xBF表示名,ユーザー名,パスワード,性別,学年,ライセンスコード\n"
        . "有効な人,valid001,password8,,,CSV-VALID\n"
        . "重複する人,valid001,password8,,,CSV-VALID\n";

    $this->actingAs($admin)
        ->post(route('admin.system.users.import'), [
            'csv_file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'CSVを取り込めませんでした。内容を確認してください。')
        ->assertSessionHas('import_errors');

    expect(User::whereIn('username', ['valid001'])->exists())->toBeFalse();
});
