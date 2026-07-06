<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultSystemAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'KANRI'],
            [
                'name' => 'システム管理者',
                'email' => null,
                'password' => Hash::make('KANRI'),
                'is_admin' => true,
                'gender' => null,
                'grade_level' => null,
            ]
        );
    }
}
