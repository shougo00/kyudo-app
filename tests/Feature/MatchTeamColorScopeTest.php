<?php

use App\Models\Group;
use App\Models\MatchTeam;
use App\Models\User;

it('allocates and reuses automatic colors independently in each record scope', function ($division, $firstColor, $secondColor) {
    $host = User::factory()->create(['is_admin' => true]);
    $group = Group::create([
        'name' => 'Independent Team Colors',
        'host_user_id' => $host->id,
        'invite_code' => '9727',
    ]);
    $group->users()->attach($host->id);
    $this->actingAs($host);

    $createTeam = function ($scope, $name, $teamDivision) use ($group) {
        $this->post("/group/{$group->id}/match-teams", [
            'date' => '2026-09-24',
            'name' => $name,
            'division' => $teamDivision,
            'record_scope' => $scope,
            'tate_size' => 3,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return MatchTeam::where('group_id', $group->id)->where('name', $name)->firstOrFail();
    };

    $officialA = $createTeam('official', 'Official A', $division);
    $selfA = $createTeam('self', 'Self A', $division);
    $selfB = $createTeam('self', 'Self B', $division);
    $officialB = $createTeam('official', 'Official B', $division);

    expect($officialA->color)->toBe($firstColor)
        ->and($selfA->color)->toBe($firstColor)
        ->and($selfB->color)->toBe($secondColor)
        ->and($officialB->color)->toBe($secondColor);

    // A deletion in one scope must not release the color in the other scope.
    $selfA->delete();
    $replacement = $createTeam('self', 'Self replacement', $division);
    expect($replacement->color)->toBe($firstColor);

    // Changing division also assigns a color within that team's scope.
    foreach (['official' => $officialB, 'self' => $selfB] as $scope => $secondTeam) {
        $secondTeam->delete();
        $switchingTeam = $createTeam($scope, 'Switch division ' . $scope, 'mixed');
        $this->patch("/match-teams/{$switchingTeam->id}", [
            'name' => $switchingTeam->name,
            'division' => $division,
            'tate_size' => 3,
        ])->assertSessionHasNoErrors()->assertRedirect();
        expect($switchingTeam->fresh()->color)->toBe($secondColor);
    }
})->with([
    'male' => ['male', '#0d6efd', '#0dcaf0'],
    'female' => ['female', '#dc3545', '#e83e8c'],
]);

it('calculates display colors independently for teams without stored colors', function () {
    $date = '2026-09-24';
    $host = User::factory()->create(['is_admin' => true]);
    $group = Group::create([
        'name' => 'Independent Display Colors',
        'host_user_id' => $host->id,
        'invite_code' => '9728',
    ]);
    $group->users()->attach($host->id);
    $teams = collect();

    foreach (['official', 'self', 'official', 'self'] as $scope) {
        $teams->push(MatchTeam::create([
            'group_id' => $group->id,
            'record_scope' => $scope,
            'date' => $date,
            'name' => $scope . $teams->count(),
            'division' => 'male',
            'color' => null,
            'tate_size' => 3,
        ]));
    }

    foreach (['match-records', 'self-match-records'] as $page) {
        $this->actingAs($host)->get("/group/{$group->id}/{$page}?date={$date}")
            ->assertOk()
            ->assertViewHas('matchTeamColorsById', function ($colors) use ($teams) {
                return $teams->map(fn ($team) => $colors->get($team->id))->all()
                    === ['#0d6efd', '#0d6efd', '#0dcaf0', '#0dcaf0'];
            });
    }
});
