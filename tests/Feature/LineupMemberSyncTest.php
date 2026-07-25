<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('keeps one lineup member per active user when group membership rows are duplicated', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Member One',
        'username' => 'member',
        'is_admin' => false,
    ]);

    $group = Group::create([
        'name' => 'Test Group',
        'host_user_id' => $host->id,
        'invite_code' => '2468',
    ]);

    DB::table('group_user')->insert([
        [
            'group_id' => $group->id,
            'user_id' => $host->id,
            'deleted_at' => null,
        ],
        [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'deleted_at' => null,
        ],
        [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'deleted_at' => null,
        ],
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}");

    $response->assertOk();

    $lineup = Lineup::where('group_id', $group->id)
        ->where('date', $date)
        ->firstOrFail();
    $memberRows = LineupMember::where('lineup_id', $lineup->id)
        ->where('user_id', $member->id)
        ->get();

    expect($memberRows)->toHaveCount(1);
    expect(LineupMember::where('lineup_id', $lineup->id)->where('user_id', $host->id)->exists())->toBeFalse();

    $responseContent = $response->getContent();

    expect(substr_count($responseContent, 'data-id="' . $memberRows->first()->id . '"'))->toBe(1);

    $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}")
        ->assertOk();

    expect(LineupMember::where('lineup_id', $lineup->id)->where('user_id', $member->id)->count())->toBe(1);
});

it('does not add lineup members repeatedly when the database unique index is missing', function () {
    Schema::table('lineup_members', function (Blueprint $table) {
        $table->dropUnique('lineup_members_lineup_user_unique');
    });

    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-no-index',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Member One',
        'username' => 'member-no-index',
        'is_admin' => false,
    ]);

    $group = Group::create([
        'name' => 'No Index Group',
        'host_user_id' => $host->id,
        'invite_code' => '1357',
    ]);

    $group->users()->attach([$host->id, $member->id]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}")
        ->assertOk();

    $lineup = Lineup::where('group_id', $group->id)
        ->where('date', $date)
        ->firstOrFail();

    expect(LineupMember::where('lineup_id', $lineup->id)->where('user_id', $member->id)->count())->toBe(1);

    $this->actingAs($host)
        ->get("/group/{$group->id}/records?date={$date}")
        ->assertOk();

    $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}")
        ->assertOk();

    expect(LineupMember::where('lineup_id', $lineup->id)->where('user_id', $member->id)->count())->toBe(1);
});

it('renders one lineup member when duplicate lineup member rows already exist', function () {
    Schema::table('lineup_members', function (Blueprint $table) {
        $table->dropUnique('lineup_members_lineup_user_unique');
    });

    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-duplicate-row',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Member One',
        'username' => 'member-duplicate-row',
        'is_admin' => false,
    ]);

    $group = Group::create([
        'name' => 'Duplicate Row Group',
        'host_user_id' => $host->id,
        'invite_code' => '9753',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $lineup = Lineup::create([
        'group_id' => $group->id,
        'date' => $date,
        'tate_size' => 3,
    ]);
    $keptMember = LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => 1,
        'is_absent' => false,
        'is_late' => false,
    ]);
    $duplicateMember = LineupMember::create([
        'lineup_id' => $lineup->id,
        'user_id' => $member->id,
        'position' => null,
        'is_absent' => false,
        'is_late' => false,
    ]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}");

    $response->assertOk();
    $response->assertSee('data-id="' . $keptMember->id . '"', false);
    $response->assertDontSee('data-id="' . $duplicateMember->id . '"', false);

    expect(LineupMember::where('lineup_id', $lineup->id)->where('user_id', $member->id)->count())->toBe(2);
});

it('shows the official record compact empty cells switch on the lineup page', function () {
    $date = '2026-07-25';
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'host-compact-toggle',
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'name' => 'Member One',
        'username' => 'member-compact-toggle',
        'is_admin' => false,
    ]);

    $group = Group::create([
        'name' => 'Compact Toggle Group',
        'host_user_id' => $host->id,
        'invite_code' => '8642',
    ]);
    $group->users()->attach([$host->id, $member->id]);

    $response = $this->actingAs($host)
        ->get("/group/{$group->id}/lineup?date={$date}");

    $response->assertOk();
    $response->assertSee('空マスを詰める');
    $response->assertSee('id="compactEmptyCellsToggle"', false);
    $response->assertSee('id="officialRecordsReturnLink"', false);
    $response->assertSee('compact_empty_slots=1', false);
    $response->assertSee('checked', false);
});
