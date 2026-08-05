<?php

use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only assigned published news and marks it as read when opened', function () {
    $user = User::factory()->create(['username' => 'user001']);
    $otherUser = User::factory()->create(['username' => 'user002']);

    $assignedNews = News::create([
        'title' => '対象のお知らせ',
        'body' => '本文です',
        'is_published' => true,
    ]);
    $assignedNews->recipients()->attach($user->id);

    $otherNews = News::create([
        'title' => '別ユーザーのお知らせ',
        'body' => '表示されない本文です',
        'is_published' => true,
    ]);
    $otherNews->recipients()->attach($otherUser->id);

    expect($user->unreadNewsCount())->toBe(1);

    $this->actingAs($user)
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('対象のお知らせ')
        ->assertDontSee('別ユーザーのお知らせ');

    $this->actingAs($user)
        ->get(route('news.show', $assignedNews))
        ->assertOk()
        ->assertSee('本文です');

    expect($user->fresh()->unreadNewsCount())->toBe(0);
});

it('lets KANRI create news for selected users', function () {
    $admin = User::where('username', 'KANRI')->firstOrFail();
    $recipient = User::factory()->create(['username' => 'recipient001']);

    $this->actingAs($admin)
        ->post(route('admin.news.store'), [
            'title' => '配信テスト',
            'body' => '配信本文',
            'is_published' => '1',
            'recipient_ids' => [$recipient->id],
        ])
        ->assertRedirect(route('admin.news.index'));

    $this->assertDatabaseHas('news', [
        'title' => '配信テスト',
        'is_published' => true,
    ]);

    $news = News::where('title', '配信テスト')->firstOrFail();

    $this->assertDatabaseHas('news_user', [
        'news_id' => $news->id,
        'user_id' => $recipient->id,
        'read_at' => null,
    ]);
});
