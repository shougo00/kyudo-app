<?php

use App\Models\Group;
use App\Models\MatchTeam;
use App\Models\MatchTeamMember;
use App\Models\MatchTateMeta;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;

function createOfficialRecordForMatchTateDeletion(User $user, string $date, int $tateNo, ?string $result = null): Record
{
    $record = Record::create([
        'user_id' => $user->id,
        'date' => $date,
        'tate_no' => $tateNo,
        'practice_type' => 'official',
        'official_sheet_no' => 1,
    ]);

    foreach (range(1, 4) as $shotNo) {
        Shot::create([
            'record_id' => $record->id,
            'shot_no' => $shotNo,
            'result' => $shotNo === 1 ? $result : null,
        ]);
    }

    return $record;
}

function createMatchTeamForTateDeletion(string $date, string $inviteCode): array
{
    $host = User::factory()->create([
        'username' => "host-delete-match-tate-{$inviteCode}",
        'is_admin' => true,
    ]);
    $member = User::factory()->create([
        'username' => "member-delete-match-tate-{$inviteCode}",
        'is_admin' => false,
    ]);
    $group = Group::create([
        'name' => "Delete Match Tate {$inviteCode}",
        'host_user_id' => $host->id,
        'invite_code' => $inviteCode,
    ]);
    $group->users()->attach([$host->id, $member->id]);
    $team = MatchTeam::create([
        'group_id' => $group->id,
        'date' => $date,
        'name' => 'Aチーム',
        'division' => 'mixed',
        'tate_size' => 1,
    ]);

    return [$host, $member, $group, $team];
}

it('deletes only the latest unscored match tate', function () {
    $date = '2026-08-02';
    [$host, $member, $group, $team] = createMatchTeamForTateDeletion($date, '7301');
    $firstRecord = createOfficialRecordForMatchTateDeletion($member, $date, 1, 'hit');
    $secondRecord = createOfficialRecordForMatchTateDeletion($member, $date, 2);

    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $member->id,
        'tate_no' => 1,
        'position' => 1,
        'official_record_id' => $firstRecord->id,
        'is_absent' => false,
        'is_late' => false,
    ]);
    MatchTeamMember::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'user_id' => $member->id,
        'tate_no' => 2,
        'position' => 1,
        'official_record_id' => $secondRecord->id,
        'is_absent' => false,
        'is_late' => false,
    ]);
    MatchTateMeta::create([
        'match_team_id' => $team->id,
        'date' => $date,
        'tate_no' => 2,
        'elapsed_seconds' => 12,
    ]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}&team_id={$team->id}")
        ->assertOk()
        ->assertSee("/match-teams/{$team->id}/tates/2", false)
        ->assertSee('ー立', false)
        ->assertSee('2立目を削除しますか？', false)
        ->assertDontSee("/match-teams/{$team->id}/tates/1", false);

    $this->actingAs($host)
        ->delete("/match-teams/{$team->id}/tates/2", [
            'date' => $date,
            'month' => '2026-08',
        ])
        ->assertRedirect("/group/{$group->id}/match-records?date={$date}&month=2026-08&team_id={$team->id}&tate_no=1#match-team-{$team->id}");

    $this->assertDatabaseMissing('match_team_members', [
        'match_team_id' => $team->id,
        'date' => $date,
        'tate_no' => 2,
    ]);
    $this->assertDatabaseMissing('match_tate_metas', [
        'match_team_id' => $team->id,
        'date' => $date,
        'tate_no' => 2,
    ]);
    expect(Record::whereKey($secondRecord->id)->exists())->toBeTrue();
});

it('does not delete the latest match tate after a score is entered', function () {
    $date = '2026-08-02';
    [$host, $member, $group, $team] = createMatchTeamForTateDeletion($date, '7302');
    $firstRecord = createOfficialRecordForMatchTateDeletion($member, $date, 1, 'hit');
    $secondRecord = createOfficialRecordForMatchTateDeletion($member, $date, 2, 'miss');

    foreach ([1 => $firstRecord, 2 => $secondRecord] as $tateNo => $record) {
        MatchTeamMember::create([
            'match_team_id' => $team->id,
            'date' => $date,
            'user_id' => $member->id,
            'tate_no' => $tateNo,
            'position' => 1,
            'official_record_id' => $record->id,
            'is_absent' => false,
            'is_late' => false,
        ]);
    }

    $this->actingAs($host)
        ->get("/group/{$group->id}/match-records?date={$date}&team_id={$team->id}")
        ->assertOk()
        ->assertSee("/match-teams/{$team->id}/tates/2", false)
        ->assertSee('2立目を削除しますか？', false);

    $this->actingAs($host)
        ->delete("/match-teams/{$team->id}/tates/2", [
            'date' => $date,
            'month' => '2026-08',
        ])
        ->assertRedirect("/group/{$group->id}/match-records?date={$date}&month=2026-08&team_id={$team->id}&tate_no=2#match-team-{$team->id}")
        ->assertSessionHas('error', '2立目に的中が入力されているため削除できません。');

    $this->assertDatabaseHas('match_team_members', [
        'match_team_id' => $team->id,
        'date' => $date,
        'tate_no' => 2,
        'official_record_id' => $secondRecord->id,
    ]);
});
