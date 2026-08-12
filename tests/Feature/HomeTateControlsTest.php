<?php

use App\Models\Group;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;

function createRecordWithShotsForTateControls(User $user, string $practiceType): void
{
    $record = Record::create([
        'user_id' => $user->id,
        'date' => '2026-08-02',
        'tate_no' => 1,
        'practice_type' => $practiceType,
    ]);

    foreach (range(1, 4) as $shotNo) {
        Shot::create([
            'record_id' => $record->id,
            'shot_no' => $shotNo,
        ]);
    }
}

it('opens personal records on self practice by default', function () {
    $user = User::factory()->create(['is_admin' => false]);
    createRecordWithShotsForTateControls($user, 'official');
    createRecordWithShotsForTateControls($user, 'self');

    $this->actingAs($user)
        ->get(route('home', [
            'date' => '2026-08-02',
        ]))
        ->assertOk()
        ->assertSee('data-type="self"', false)
        ->assertSee('自主練')
        ->assertSee('btn-primary', false);
});

it('shows official tate controls when the user is not in a group', function () {
    $user = User::factory()->create(['is_admin' => false]);
    createRecordWithShotsForTateControls($user, 'official');

    $this->actingAs($user)
        ->get(route('home', [
            'date' => '2026-08-02',
            'type' => 'official',
        ]))
        ->assertOk()
        ->assertSee('＋ 立を追加', false)
        ->assertSee('delete-record', false);
});

it('hides official tate controls when the user is in a group', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $host = User::factory()->create(['is_admin' => true]);
    $group = Group::create([
        'name' => 'Kyudo Club',
        'host_user_id' => $host->id,
        'invite_code' => '8426',
    ]);
    $group->users()->attach($user->id);
    createRecordWithShotsForTateControls($user, 'official');

    $this->actingAs($user)
        ->get(route('home', [
            'date' => '2026-08-02',
            'type' => 'official',
        ]))
        ->assertOk()
        ->assertDontSee('＋ 立を追加', false)
        ->assertDontSee('delete-record', false);
});

it('shows self tate controls when the user is in a group', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $host = User::factory()->create(['is_admin' => true]);
    $group = Group::create([
        'name' => 'Kyudo Club',
        'host_user_id' => $host->id,
        'invite_code' => '4826',
    ]);
    $group->users()->attach($user->id);
    createRecordWithShotsForTateControls($user, 'self');

    $this->actingAs($user)
        ->get(route('home', [
            'date' => '2026-08-02',
            'type' => 'self',
        ]))
        ->assertOk()
        ->assertSee('＋ 立を追加', false)
        ->assertSee('delete-record', false);
});
