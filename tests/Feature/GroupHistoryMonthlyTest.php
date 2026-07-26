<?php

use App\Models\Group;
use App\Models\User;

it('orders monthly group records by grade and puts users without a grade last', function () {
    $host = User::factory()->create([
        'name' => 'Host',
        'username' => 'monthly-host',
        'is_admin' => true,
    ]);

    $thirdGrade = User::factory()->create([
        'name' => 'Third Grade',
        'username' => 'monthly-third',
        'is_admin' => false,
        'grade_level' => 3,
    ]);

    $secondGrade = User::factory()->create([
        'name' => 'Second Grade',
        'username' => 'monthly-second',
        'is_admin' => false,
        'grade_level' => 2,
    ]);

    $firstGrade = User::factory()->create([
        'name' => 'First Grade',
        'username' => 'monthly-first',
        'is_admin' => false,
        'grade_level' => 1,
    ]);

    $noGrade = User::factory()->create([
        'name' => 'No Grade',
        'username' => 'monthly-no-grade',
        'is_admin' => false,
        'grade_level' => null,
    ]);

    $group = Group::create([
        'name' => 'Monthly Grade Order Group',
        'host_user_id' => $host->id,
        'invite_code' => '8642',
        'uses_grades' => true,
        'grade_count' => 3,
    ]);

    $group->users()->attach([
        $host->id,
        $firstGrade->id,
        $noGrade->id,
        $thirdGrade->id,
        $secondGrade->id,
    ]);

    $this->actingAs($host)
        ->get("/group/{$group->id}/history?view=monthly&month=2026-07")
        ->assertOk()
        ->assertSeeInOrder([
            'Third Grade',
            'Second Grade',
            'First Grade',
            'No Grade',
        ]);
});
