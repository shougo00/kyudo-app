<?php

use App\Models\Record;
use App\Models\Shot;
use App\Models\User;

it('shows monthly hit rates for each shot position and practice type', function () {
    $user = User::factory()->create();

    $createRecord = function (string $date, string $practiceType, array $results) use ($user) {
        $record = Record::create([
            'user_id' => $user->id,
            'date' => $date,
            'tate_no' => 1,
            'practice_type' => $practiceType,
        ]);

        foreach ($results as $index => $result) {
            Shot::create([
                'record_id' => $record->id,
                'shot_no' => $index + 1,
                'result' => $result,
            ]);
        }
    };

    $createRecord('2026-04-02', 'official', ['hit', 'miss', 'hit', null]);
    $createRecord('2026-04-12', 'official', ['miss', 'hit', 'hit', 'miss']);
    $createRecord('2026-04-20', 'self', ['hit', 'hit', 'miss', 'hit']);
    $createRecord('2026-03-31', 'official', ['hit', 'hit', 'hit', 'hit']);

    $response = $this->actingAs($user)
        ->get('/dashboard?month=2026-04&type=all')
        ->assertOk()
        ->assertSee('data-shot-position-summary', false)
        ->assertSee('1射目')
        ->assertSee('4射目');

    $response->assertViewHas('monthShotPositions', function (array $stats) {
        return $stats['official'][1] === ['shots' => 2, 'hits' => 1, 'rate' => 50.0]
            && $stats['official'][3] === ['shots' => 2, 'hits' => 2, 'rate' => 100.0]
            && $stats['official'][4] === ['shots' => 1, 'hits' => 0, 'rate' => 0.0]
            && $stats['self'][1] === ['shots' => 1, 'hits' => 1, 'rate' => 100.0]
            && $stats['self'][3] === ['shots' => 1, 'hits' => 0, 'rate' => 0.0]
            && $stats['all'][1] === ['shots' => 3, 'hits' => 2, 'rate' => 66.7]
            && $stats['all'][4] === ['shots' => 2, 'hits' => 1, 'rate' => 50.0];
    });
});
