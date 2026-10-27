<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\MatchTeam;
use App\Models\MatchTeamMember;
use App\Models\Record;
use App\Models\Shot;
use App\Models\ShotChangeLog;
use App\Models\User;
use App\Services\ShotUpdater;
use Carbon\Carbon;

beforeEach(function () {
    $this->host = User::factory()->create(['name' => '記録担当', 'username' => 'shot-log-host', 'is_admin' => true]);
    $this->archer = User::factory()->create(['name' => '対象選手', 'username' => 'shot-log-archer', 'is_admin' => false]);
    $this->group = Group::create([
        'name' => '履歴確認グループ', 'host_user_id' => $this->host->id, 'invite_code' => 'LOG01',
        'show_group_records_to_members' => true, 'allow_members_edit_group_records' => true,
    ]);
    $this->group->users()->attach([$this->host->id, $this->archer->id]);
    $this->lineup = Lineup::create(['group_id' => $this->group->id, 'date' => '2026-10-26', 'tate_size' => 1]);
    LineupMember::create([
        'lineup_id' => $this->lineup->id, 'user_id' => $this->archer->id,
        'position' => 1, 'is_absent' => false, 'is_late' => false,
    ]);
    $this->record = Record::create([
        'user_id' => $this->archer->id, 'date' => '2026-10-26', 'tate_no' => 6,
        'practice_type' => 'official', 'official_sheet_no' => 2,
    ]);
    $this->shot = Shot::create(['record_id' => $this->record->id, 'shot_no' => 3, 'result' => null]);
    $this->admin = User::where('username', 'KANRI')->firstOrFail();
});

it('records the authenticated actor and before/after values for input edits and clearing', function () {
    $this->travelTo(Carbon::parse('2026-10-27 17:46:07.123456'));
    $this->actingAs($this->host)->postJson("/group/shot/{$this->shot->id}", [
        'result' => 'hit', 'actor_user_id' => $this->archer->id, 'old_result' => 'miss',
    ])->assertOk()->assertJson(['success' => true]);
    $this->actingAs($this->archer)->postJson("/group/shot/{$this->shot->id}", ['result' => 'miss'])->assertOk();
    $this->postJson("/group/shot/{$this->shot->id}", ['result' => null])->assertOk();

    $logs = ShotChangeLog::orderBy('id')->get();
    expect($logs)->toHaveCount(3)
        ->and($logs->pluck('old_result')->all())->toBe([null, 'hit', 'miss'])
        ->and($logs->pluck('new_result')->all())->toBe(['hit', 'miss', null])
        ->and($logs->pluck('actor_user_id')->all())->toBe([$this->host->id, $this->archer->id, $this->archer->id]);
    $log = $logs->first();
    $this->assertDatabaseHas('shot_change_logs', ['id' => $log->id, 'practice_date' => '2026-10-26']);
    expect($log->group_id)->toBe($this->group->id)
        ->and($log->group_name)->toBe('履歴確認グループ')
        ->and($log->subject_user_id)->toBe($this->archer->id)
        ->and($log->subject_name)->toBe('対象選手')
        ->and($log->practice_date->format('Y-m-d'))->toBe('2026-10-26')
        ->and($log->created_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-27 17:46:07.123456')
        ->and($log->tate_no)->toBe(6)->and($log->shot_no)->toBe(3)
        ->and($log->official_sheet_no)->toBe(2)
        ->and($log->oldValueLabel())->toBe('未入力')->and($log->newValueLabel())->toBe('○')
        ->and($logs->last()->operationLabel())->toBe('取消')
        ->and($this->shot->fresh()->result)->toBeNull();

    $this->actingAs($this->admin)->get(route('admin.system.shot-logs'))
        ->assertOk()->assertSee('未入力')->assertSee('○')->assertSee('×')
        ->assertSee('6立目・3射目')->assertSee('17:46:07.123')
        ->assertViewHas('logs', fn ($page) => $page->pluck('id')->all() === $logs->pluck('id')->reverse()->values()->all());
});

it('reads the latest saved value even if a caller holds an old shot model', function () {
    $stale = Shot::findOrFail($this->shot->id);
    $updater = app(ShotUpdater::class);
    $updater->update($this->shot, ['result' => 'hit'], $this->host, 'group');
    $updater->update($stale, ['result' => 'miss'], $this->archer, 'group');
    expect(ShotChangeLog::orderByDesc('id')->first()->old_result)->toBe('hit');
});

it('logs numeric values including zero without duplicating identical saves', function () {
    $this->actingAs($this->host);
    foreach ([0, 9, '9', null] as $value) {
        $this->postJson("/group/shot/{$this->shot->id}", ['result' => null, 'numeric_score' => $value])->assertOk();
    }
    $logs = ShotChangeLog::orderBy('id')->get();
    expect($logs)->toHaveCount(3)
        ->and($logs->pluck('old_numeric_score')->all())->toBe([null, 0, 9])
        ->and($logs->pluck('new_numeric_score')->all())->toBe([0, 9, null])
        ->and($logs->first()->newValueLabel())->toBe('0点');
});

it('logs changes to official shots from home and through a linked match team', function () {
    $this->actingAs($this->archer)->postJson("/shots/{$this->shot->id}", ['result' => 'hit'])->assertOk();
    expect(ShotChangeLog::first()->source)->toBe('home');
    $this->lineup->members()->delete();
    $this->lineup->delete();
    $team = MatchTeam::create([
        'group_id' => $this->group->id, 'date' => '2026-10-26', 'name' => '試合チーム',
        'division' => 'mixed', 'tate_size' => 1, 'record_scope' => 'official',
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id, 'user_id' => $this->archer->id, 'date' => '2026-10-26',
        'tate_no' => 1, 'position' => 1, 'official_record_id' => $this->record->id,
    ]);
    $this->actingAs($this->host)->postJson("/group/shot/{$this->shot->id}", ['result' => 'miss'])->assertOk();
    $log = ShotChangeLog::orderByDesc('id')->first();
    expect($log->group_id)->toBe($this->group->id)->and($log->tate_no)->toBe(6)
        ->and($log->old_result)->toBe('hit')->and($log->new_result)->toBe('miss');
});

it('does not add official history for self practice or standalone match records', function () {
    foreach (['self', 'match'] as $type) {
        $this->record->update(['practice_type' => $type]);
        $this->actingAs($this->host)->postJson("/group/shot/{$this->shot->id}", ['result' => 'hit'])->assertOk();
        $this->actingAs($this->archer)->postJson("/shots/{$this->shot->id}", ['result' => 'miss'])->assertOk();
    }
    expect(ShotChangeLog::count())->toBe(0);
});

it('does not record failed unauthorized or invalid updates', function () {
    $outsider = User::factory()->create();
    $this->actingAs($outsider)->postJson("/group/shot/{$this->shot->id}", ['result' => 'hit'])->assertForbidden();
    $this->group->update(['allow_members_edit_group_records' => false]);
    $this->actingAs($this->archer)->postJson("/group/shot/{$this->shot->id}", ['result' => 'hit'])->assertForbidden();
    $this->actingAs($this->host)->postJson("/group/shot/{$this->shot->id}", ['result' => 'invalid'])->assertUnprocessable();
    $this->postJson("/group/shot/{$this->shot->id}", ['numeric_score' => 1000])->assertUnprocessable();
    expect($this->shot->fresh()->result)->toBeNull()->and(ShotChangeLog::count())->toBe(0);
});

it('rolls back the shot update if its history cannot be saved', function () {
    ShotChangeLog::creating(function () {
        throw new RuntimeException('history unavailable');
    });
    try {
        expect(fn () => app(ShotUpdater::class)->update($this->shot, ['result' => 'hit'], $this->host, 'group'))
            ->toThrow(RuntimeException::class, 'history unavailable');
    } finally {
        ShotChangeLog::flushEventListeners();
    }
    expect($this->shot->fresh()->result)->toBeNull()->and(ShotChangeLog::count())->toBe(0);
});

it('retains the original names and coordinates after renaming renumbering or deletion', function () {
    app(ShotUpdater::class)->update($this->shot, ['result' => 'hit'], $this->host, 'group');
    $this->host->update(['name' => '記録担当の新しい名前']);
    $this->archer->update(['name' => '対象選手の新しい名前']);
    $this->group->update(['name' => '変更後のグループ名']);
    $this->record->update(['tate_no' => 1]);
    $this->record->delete();
    $this->group->delete();
    $this->archer->delete();
    $this->host->delete();
    expect(ShotChangeLog::count())->toBe(1);
    $this->actingAs($this->admin)->get(route('admin.system.shot-logs'))
        ->assertOk()->assertSee('記録担当')->assertSee('対象選手')->assertSee('履歴確認グループ')
        ->assertSee('6立目・3射目')->assertDontSee('対象選手の新しい名前');
});

it('restricts the history page to the system administrator and links it from the dashboard', function () {
    $url = route('admin.system.shot-logs');
    $this->get($url)->assertRedirect();
    $this->actingAs($this->host)->get($url)->assertForbidden();
    $this->actingAs($this->archer)->get($url)->assertForbidden();
    $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('条件に一致する変更履歴はありません。');
    $this->get(route('admin.system.index'))->assertOk()->assertSee($url, false);
});

it('filters by dates people group tate shot and shot ID individually or together', function () {
    app(ShotUpdater::class)->update($this->shot, ['result' => 'hit'], $this->host, 'group');
    $wanted = ShotChangeLog::first();
    $wanted->update(['created_at' => '2026-10-27 23:59:59.999999']);
    $other = $wanted->replicate();
    $other->fill([
        'group_id' => 9999, 'group_name' => '別のグループ', 'actor_user_id' => 9998,
        'actor_name' => '別の記録者', 'actor_username' => 'another-recorder', 'subject_user_id' => 9997,
        'subject_name' => '別の対象者', 'subject_username' => 'another-archer',
        'practice_date' => '2026-10-25', 'tate_no' => 2, 'shot_no' => 1, 'shot_id' => 9996,
        'created_at' => '2026-10-28 00:00:00.000000',
    ])->save();
    $filters = [
        'practice_date' => '2026-10-26', 'changed_date' => '2026-10-27', 'group' => '履歴確認',
        'actor' => '記録担当', 'subject' => '対象選手', 'tate_no' => 6, 'shot_no' => 3, 'shot_id' => $this->shot->id,
    ];
    $this->actingAs($this->admin);
    foreach ($filters as $key => $value) {
        $this->get(route('admin.system.shot-logs', [$key => $value]))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->id === $wanted->id);
    }
    foreach (['actor' => $this->host->username, 'subject' => (string) $this->archer->id, 'group' => (string) $this->group->id] as $key => $value) {
        $this->get(route('admin.system.shot-logs', [$key => $value]))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    }
    $this->get(route('admin.system.shot-logs', $filters))->assertOk()
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    $this->get(route('admin.system.shot-logs', ['actor' => '存在しない人']))->assertOk()
        ->assertSee('条件に一致する変更履歴はありません。');
    foreach (['group', 'actor', 'subject'] as $key) {
        $this->get(route('admin.system.shot-logs', [$key => '0']))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);
    }
});

it('keeps filter conditions across pages and breaks same-time ties consistently', function () {
    app(ShotUpdater::class)->update($this->shot, ['result' => 'hit'], $this->host, 'group');
    $first = ShotChangeLog::first();
    foreach (range(1, 50) as $index) {
        $first->replicate()->fill(['created_at' => $first->created_at])->save();
    }
    $this->actingAs($this->admin)->get(route('admin.system.shot-logs', ['practice_date' => '2026-10-26']))
        ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->total() === 51 && $logs->count() === 50)
        ->assertSee('practice_date=2026-10-26&amp;page=2', false);
    $this->get(route('admin.system.shot-logs', ['practice_date' => '2026-10-26', 'page' => 2]))
        ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->count() === 1 && $logs->first()->id === $first->id);
});

it('validates filters and escapes names in the history view', function () {
    $this->actingAs($this->admin)->getJson(route('admin.system.shot-logs', [
        'practice_date' => '2026-02-30', 'shot_no' => 5, 'tate_no' => -1, 'actor' => ['invalid'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['practice_date', 'shot_no', 'tate_no', 'actor']);
    $this->host->update(['name' => '<script>alert(1)</script>']);
    app(ShotUpdater::class)->update($this->shot, ['result' => 'hit'], $this->host, 'group');
    $this->get(route('admin.system.shot-logs'))->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});
