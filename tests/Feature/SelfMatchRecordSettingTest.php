<?php

use App\Models\Group;
use App\Models\User;

it('defaults the self match button to hidden and persists the group visibility setting', function () {
    $host = User::factory()->create(['is_admin' => true]);
    $group = Group::create([
        'name' => 'Self Match Settings',
        'host_user_id' => $host->id,
        'invite_code' => '9725',
    ]);
    $group->users()->attach($host->id);
    $selfUrl = route('group.self-records', $group);
    $matchUrl = route('group.self-match-records', ['groupId' => $group->id, 'date' => now()->toDateString()]);
    $settings = ['official_tates_per_page' => 5, 'grade_count' => 3];

    expect($group->fresh()->uses_self_match_records)->toBeFalse();
    $this->actingAs($host)->get($selfUrl)->assertOk()->assertDontSee($matchUrl, false);

    // Changing this group setting requires the existing settings unlock.
    $this->patch(route('settings.update'), $settings + ['uses_self_match_records' => 1])
        ->assertRedirect(route('settings.index'));
    expect($group->fresh()->uses_self_match_records)->toBeFalse();

    $this->withSession(["settings_unlocked_group_{$group->id}" => true]);
    $this->get(route('settings.index'))->assertOk()
        ->assertSee('グループ的中記録（自主練）に試合形式記録を使用する');

    $this->patch(route('settings.update'), $settings + ['uses_self_match_records' => 1])
        ->assertSessionHasNoErrors();
    expect($group->fresh()->uses_self_match_records)->toBeTrue();
    $this->get($selfUrl)->assertOk()->assertSee($matchUrl, false);

    $this->patch(route('settings.update'), $settings + ['uses_self_match_records' => 0])
        ->assertSessionHasNoErrors();
    expect($group->fresh()->uses_self_match_records)->toBeFalse();
    $this->get($selfUrl)->assertOk()->assertDontSee($matchUrl, false);
});
