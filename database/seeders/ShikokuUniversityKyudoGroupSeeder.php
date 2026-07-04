<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ShikokuUniversityKyudoGroupSeeder extends Seeder
{
    public function run(): void
    {
        $host = User::updateOrCreate(
            ['username' => 'shikoku'],
            [
                'name' => '四国大学弓道部',
                'email' => null,
                'password' => Hash::make('shikoku'),
                'is_admin' => true,
                'gender' => null,
                'grade_level' => null,
            ]
        );

        $group = Group::updateOrCreate(
            ['name' => '四国大学弓道部'],
            [
                'host_user_id' => $host->id,
                'invite_code' => 'SHIKOKU-KYUDO',
                'official_tates_per_page' => 5,
                'uses_grades' => true,
                'grade_count' => 4,
                'grade_colors' => [
                    1 => '#dbeafe',
                    2 => '#fee2e2',
                    3 => '#dcfce7',
                    4 => '#fef3c7',
                ],
            ]
        );

        $studentIds = User::whereIn('username', $this->studentNumbers())
            ->pluck('id')
            ->all();

        $group->users()->syncWithoutDetaching(array_merge([$host->id], $studentIds));

        $oldHost = User::where('username', 'shikokukyudo')->first();

        if ($oldHost) {
            $group->users()->detach($oldHost->id);
            $oldHost->delete();
        }
    }

    private function studentNumbers(): array
    {
        return [
            '202331001',
            '202337017',
            '202331086',
            '202325077',
            '202325078',
            '202425005',
            '202425029',
            '202437040',
            '202431078',
            '202537006',
            '202523007',
            '202525027',
            '202640004',
            '202618003',
            '202615019',
            '202640054',
            '202631075',
            '202631085',
            '202640084',
            '202615037',
            '202325002',
            '202323018',
            '202325021',
            '202337070',
            '202325031',
            '202325071',
            '202420021',
            '202423043',
            '202431075',
            '202531019',
            '202526005',
            '202531025',
            '202525009',
            '202520086',
            '202631003',
            '202615049',
            '202620007',
            '202625019',
            '202631046',
            '202631052',
        ];
    }
}
