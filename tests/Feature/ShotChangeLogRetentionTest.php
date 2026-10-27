<?php

use App\Models\Record;
use App\Models\Shot;
use App\Models\ShotChangeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->admin = User::where('username', 'KANRI')->firstOrFail();
});

function createShotRetentionLog(string $createdAt, array $attributes = []): ShotChangeLog
{
    return ShotChangeLog::create(array_merge([
        'record_id' => 1,
        'shot_id' => 1,
        'actor_user_id' => 1,
        'actor_name' => '記録者',
        'actor_username' => 'recorder',
        'subject_user_id' => 2,
        'subject_name' => '対象者',
        'subject_username' => 'archer',
        'practice_date' => '2026-01-01',
        'official_sheet_no' => 1,
        'tate_no' => 1,
        'shot_no' => 1,
        'old_result' => null,
        'new_result' => 'hit',
        'source' => 'group',
        'created_at' => $createdAt,
    ], $attributes));
}

it('deletes only expired logs after the system admin presses the delete button', function () {
    $this->travelTo(Carbon::parse('2026-10-27 03:00:00.123456', 'Asia/Tokyo'));
    $user = User::factory()->create();
    $record = Record::create([
        'user_id' => $user->id, 'date' => '2026-01-01', 'tate_no' => 1, 'practice_type' => 'official',
    ]);
    $shot = Shot::create(['record_id' => $record->id, 'shot_no' => 1, 'result' => 'hit']);
    $old = createShotRetentionLog('2026-09-27 03:00:00.123455', ['record_id' => $record->id, 'shot_id' => $shot->id]);
    $boundary = createShotRetentionLog('2026-09-27 03:00:00.123456');
    $recent = createShotRetentionLog('2026-10-27 02:00:00.000000');

    $page = $this->actingAs($this->admin)->get(route('admin.system.shot-logs', ['actor' => 'no matching actor']))
        ->assertOk()->assertViewHas('expiredLogCount', 1)
        ->assertSee('1か月より古い履歴を削除（1件）')
        ->assertSee('name="_token"', false)->assertSee('onsubmit="return confirm(', false);
    expect(ShotChangeLog::count())->toBe(3);

    $this->delete(route('admin.system.shot-logs.destroy-expired'), [
        'cutoff' => $page->viewData('deletionCutoff')->format('Y-m-d H:i:s.u'),
    ])->assertRedirect(route('admin.system.shot-logs'))
        ->assertSessionHas('status', '1件の古い変更履歴を削除しました。');

    expect(ShotChangeLog::find($old->id))->toBeNull()
        ->and(ShotChangeLog::orderBy('id')->pluck('id')->all())->toBe([$boundary->id, $recent->id])
        ->and($record->fresh())->not->toBeNull()
        ->and($shot->fresh()->result)->toBe('hit')
        ->and($user->fresh())->not->toBeNull();
});

it('uses a calendar month without overflowing short months or years', function (string $now, string $cutoff) {
    $this->travelTo(Carbon::parse($now, 'Asia/Tokyo'));
    $old = createShotRetentionLog(Carbon::parse($cutoff)->subMicrosecond()->format('Y-m-d H:i:s.u'));
    $keep = createShotRetentionLog($cutoff);

    $this->actingAs($this->admin)->delete(route('admin.system.shot-logs.destroy-expired'), [
        'cutoff' => ShotChangeLog::retentionCutoff()->format('Y-m-d H:i:s.u'),
    ])->assertRedirect();

    expect(ShotChangeLog::find($old->id))->toBeNull()
        ->and(ShotChangeLog::pluck('id')->all())->toBe([$keep->id]);
})->with([
    'short February' => ['2026-03-31 03:00:00', '2026-02-28 03:00:00'],
    'leap February' => ['2024-03-31 03:00:00', '2024-02-29 03:00:00'],
    'previous year' => ['2027-01-31 03:00:00', '2026-12-31 03:00:00'],
]);

it('deletes expired logs across multiple batches and can be repeated safely', function () {
    $this->travelTo(Carbon::parse('2026-10-27 03:00:00', 'Asia/Tokyo'));
    $old = createShotRetentionLog('2026-09-01 00:00:00');
    $attributes = $old->getAttributes();
    unset($attributes['id']);
    foreach (array_chunk(array_fill(0, 1001, $attributes), 100) as $batch) {
        ShotChangeLog::insert($batch);
    }
    $keep = createShotRetentionLog('2026-10-26 00:00:00');
    $this->actingAs($this->admin);
    foreach (range(1, 2) as $run) {
        $this->delete(route('admin.system.shot-logs.destroy-expired'), [
            'cutoff' => ShotChangeLog::retentionCutoff()->format('Y-m-d H:i:s.u'),
        ])->assertRedirect();
        expect(ShotChangeLog::pluck('id')->all())->toBe([$keep->id]);
    }
});

it('does not automatically prune logs through views or the scheduler', function () {
    $this->travelTo(Carbon::parse('2026-10-27 03:00:00', 'Asia/Tokyo'));
    $old = createShotRetentionLog('2026-09-01 00:00:00');
    $this->actingAs($this->admin)->get(route('admin.system.shot-logs'))->assertOk();

    expect(ShotChangeLog::pluck('id')->all())->toBe([$old->id])
        ->and(ShotChangeLog::isPrunable())->toBeFalse();
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'model:prune'));
    expect($events)->toBeEmpty();
});

it('rejects deletion by guests and group administrators', function () {
    $old = createShotRetentionLog('2026-01-01 00:00:00');
    $url = route('admin.system.shot-logs.destroy-expired');
    $data = ['cutoff' => ShotChangeLog::retentionCutoff()->format('Y-m-d H:i:s.u')];
    $this->delete($url, $data)->assertRedirect();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->delete($url, $data)->assertForbidden();
    expect(ShotChangeLog::pluck('id')->all())->toBe([$old->id]);
});

it('cannot delete recent history by posting a forged future cutoff', function () {
    $this->travelTo(Carbon::parse('2026-10-27 03:00:00', 'Asia/Tokyo'));
    createShotRetentionLog('2026-09-01 00:00:00');
    $boundary = createShotRetentionLog('2026-09-27 03:00:00');
    $recent = createShotRetentionLog('2026-10-26 00:00:00');
    $this->actingAs($this->admin)->delete(route('admin.system.shot-logs.destroy-expired'), [
        'cutoff' => '2099-01-01 00:00:00.000000',
    ])->assertRedirect();
    expect(ShotChangeLog::orderBy('id')->pluck('id')->all())->toBe([$boundary->id, $recent->id]);
});

it('keeps logs newer than the cutoff displayed when the page was opened', function () {
    $this->travelTo(Carbon::parse('2026-10-27 03:00:00', 'Asia/Tokyo'));
    createShotRetentionLog('2026-09-01 00:00:00');
    $keep = createShotRetentionLog('2026-09-27 12:00:00');
    $cutoff = $this->actingAs($this->admin)->get(route('admin.system.shot-logs'))
        ->viewData('deletionCutoff')->format('Y-m-d H:i:s.u');
    $this->travelTo(Carbon::parse('2026-10-28 03:00:00', 'Asia/Tokyo'));
    $this->delete(route('admin.system.shot-logs.destroy-expired'), ['cutoff' => $cutoff])->assertRedirect();
    expect(ShotChangeLog::pluck('id')->all())->toBe([$keep->id]);
});

it('validates the cutoff and disables the button when there are no expired logs', function () {
    $this->actingAs($this->admin);
    foreach ([[], ['cutoff' => 'invalid']] as $data) {
        $this->deleteJson(route('admin.system.shot-logs.destroy-expired'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('cutoff');
    }
    $this->get(route('admin.system.shot-logs'))->assertOk()
        ->assertViewHas('expiredLogCount', 0)->assertSee('class="btn btn-outline-danger" disabled', false);
});
