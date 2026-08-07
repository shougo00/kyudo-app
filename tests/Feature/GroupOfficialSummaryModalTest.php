<?php

use App\Models\Group;
use App\Models\Lineup;
use App\Models\LineupMember;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;

it('renders official daily summaries from the bottom name row', function () {
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-08-08 12:00:00'));

    try {
        $date = '2026-08-08';
        $host = User::factory()->create([
            'name' => 'Host',
            'username' => 'official-summary-host',
            'is_admin' => true,
        ]);
        $member = User::factory()->create([
            'name' => 'Summary Archer',
            'username' => 'official-summary-archer',
            'is_admin' => false,
        ]);
        $group = Group::create([
            'name' => 'Official Summary Group',
            'host_user_id' => $host->id,
            'invite_code' => '6412',
        ]);
        $group->users()->attach([$host->id, $member->id]);

        $lineup = Lineup::create([
            'group_id' => $group->id,
            'date' => $date,
            'tate_size' => 1,
        ]);
        LineupMember::create([
            'lineup_id' => $lineup->id,
            'user_id' => $member->id,
            'position' => 1,
            'is_absent' => false,
            'is_late' => false,
        ]);

        $record = Record::create([
            'user_id' => $member->id,
            'date' => $date,
            'tate_no' => 1,
            'practice_type' => 'official',
            'official_sheet_no' => 1,
            'lineup_position' => 1,
            'lineup_tate_size' => 1,
        ]);

        foreach ([1 => 'hit', 2 => 'hit', 3 => 'miss', 4 => null] as $shotNo => $result) {
            Shot::create([
                'record_id' => $record->id,
                'shot_no' => $shotNo,
                'result' => $result,
            ]);
        }

        $response = $this->actingAs($host)
            ->get("/group/{$group->id}/records?date={$date}")
            ->assertOk()
            ->assertSee('official-summary-trigger', false)
            ->assertSee('data-official-summary-name="Summary Archer"', false)
            ->assertSee('data-official-summary-shots="3"', false)
            ->assertSee('data-official-summary-hits="2"', false)
            ->assertSee('data-official-summary-rate="66.7"', false);

        $html = $response->getContent();
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $modal = (new DOMXPath($document))
            ->query('//*[@id="officialRecordSummaryModal"]')
            ->item(0);

        expect($modal)->not->toBeNull();

        $isInsidePrintOnly = false;
        for ($ancestor = $modal->parentNode; $ancestor; $ancestor = $ancestor->parentNode) {
            if (
                $ancestor instanceof DOMElement
                && preg_match('/(^|\s)print-only(\s|$)/', $ancestor->getAttribute('class'))
            ) {
                $isInsidePrintOnly = true;
                break;
            }
        }

        expect($isInsidePrintOnly)->toBeFalse();
    } finally {
        \Carbon\Carbon::setTestNow();
    }
});
