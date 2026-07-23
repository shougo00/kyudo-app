<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoKyudoGroupSeeder extends Seeder
{
    public function run(): void
    {
        $host = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'デモ管理者',
                'email' => null,
                'password' => Hash::make('admin'),
                'is_admin' => true,
                'gender' => null,
                'grade_level' => null,
            ]
        );

        $group = Group::where('invite_code', '4040')->first()
            ?? Group::where('name', 'デモ弓道部')->first()
            ?? new Group();

        $group->fill([
            'name' => 'デモ弓道部',
            'host_user_id' => $host->id,
            'invite_code' => '4040',
            'official_tates_per_page' => 5,
            'show_group_records_to_members' => true,
            'allow_members_edit_group_records' => true,
            'uses_grades' => true,
            'grade_count' => 4,
            'grade_colors' => [
                1 => '#dbeafe',
                2 => '#fee2e2',
                3 => '#dcfce7',
                4 => '#fef3c7',
            ],
            'numeric_score_options' => [
                ['value' => 1, 'color' => '#dbeafe'],
                ['value' => 2, 'color' => '#dcfce7'],
                ['value' => 3, 'color' => '#fef3c7'],
            ],
        ])->save();

        $memberIds = [$host->id];

        foreach ($this->members() as $member) {
            $user = User::updateOrCreate(
                ['username' => $member['username']],
                [
                    'name' => $member['name'],
                    'email' => null,
                    'password' => Hash::make($member['username']),
                    'is_admin' => false,
                    'gender' => $member['gender'],
                    'grade_level' => $member['grade_level'],
                    'all_absent' => false,
                ]
            );

            $memberIds[] = $user->id;
        }

        $group->users()->syncWithoutDetaching($memberIds);

        DB::table('group_user')
            ->where('group_id', $group->id)
            ->whereIn('user_id', $memberIds)
            ->update(['deleted_at' => null]);
    }

    private function members(): array
    {
        $members = [];
        $index = 1;

        for ($grade = 1; $grade <= 4; $grade++) {
            for ($i = 1; $i <= 5; $i++) {
                $members[] = [
                    'username' => sprintf('demo_m%02d', $index),
                    'name' => sprintf('デモ男子%02d', $index),
                    'gender' => 'male',
                    'grade_level' => $grade,
                ];
                $index++;
            }
        }

        $index = 1;

        for ($grade = 1; $grade <= 4; $grade++) {
            for ($i = 1; $i <= 5; $i++) {
                $members[] = [
                    'username' => sprintf('demo_f%02d', $index),
                    'name' => sprintf('デモ女子%02d', $index),
                    'gender' => 'female',
                    'grade_level' => $grade,
                ];
                $index++;
            }
        }

        return $members;
    }
}
