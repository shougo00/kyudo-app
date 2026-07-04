<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KyudoResult;
use Carbon\Carbon;

class KyudoResultSeeder extends Seeder
{
    public function run(): void
    {
        $userId = \App\Models\User::first()->id;

       $date = Carbon::create(2026, 4, 28);

        for ($i = 0; $i < 10; $i++) {
            KyudoResult::create([
                'user_id' => 1, // ←自分のID

                'kai_time' => rand(2000, 4000),

                'right_elbow_angle' => rand(90, 110),
                'right_armpit_angle' => rand(80, 100),
                'left_armpit_angle'  => rand(80, 100),

                'created_at' => $date->copy()->setTime(rand(8,20), rand(0,59)),
                'updated_at' => now(),
            ]);
        }
    }
}