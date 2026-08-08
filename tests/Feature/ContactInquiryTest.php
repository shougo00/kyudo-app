<?php

use App\Models\ContactInquiry;
use App\Models\User;

it('stores contact inquiries from the landing page', function () {
    $this->postJson(route('contact-inquiries.store'), [
        'groupName' => '青空高校弓道部',
        'representativeName' => '山田 太郎',
        'email' => 'yamada@example.com',
    ])
        ->assertCreated()
        ->assertJson([
            'message' => 'お問い合わせありがとうございます。担当者よりご連絡します。',
        ]);

    $this->assertDatabaseHas('contact_inquiries', [
        'group_name' => '青空高校弓道部',
        'representative_name' => '山田 太郎',
        'email' => 'yamada@example.com',
    ]);
});

it('redirects back to the landing contact section after a normal inquiry post', function () {
    $this->from('/')
        ->post(route('contact-inquiries.store'), [
            'groupName' => '朝霧中学校弓道部',
            'representativeName' => '鈴木 一郎',
            'email' => 'suzuki@example.com',
        ])
        ->assertRedirect(url('/') . '#contact')
        ->assertSessionHas('contact_success');

    $this->assertDatabaseHas('contact_inquiries', [
        'group_name' => '朝霧中学校弓道部',
        'representative_name' => '鈴木 一郎',
        'email' => 'suzuki@example.com',
    ]);
});

it('lets KANRI view contact inquiries', function () {
    $admin = User::where('username', 'KANRI')->firstOrFail();

    ContactInquiry::create([
        'group_name' => '夕凪道場',
        'representative_name' => '佐藤 花子',
        'email' => 'satou@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertSee('お問い合わせ管理')
        ->assertSee('夕凪道場');

    $this->actingAs($admin)
        ->get(route('admin.system.inquiries'))
        ->assertOk()
        ->assertSee('お問い合わせ管理')
        ->assertSee('夕凪道場')
        ->assertSee('佐藤 花子')
        ->assertSee('satou@example.com');
});

it('blocks non KANRI users from contact inquiry management', function () {
    $user = User::factory()->create([
        'username' => 'normal-inquiry-user',
        'is_admin' => false,
    ]);

    $this->actingAs($user)
        ->get(route('admin.system.inquiries'))
        ->assertForbidden();
});
