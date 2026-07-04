<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
 use Database\Seeders\ItemSeeder;
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
  

    public function run(): void
    {
        $this->call([
            ItemSeeder::class,
            StudentAccountSeeder::class,
            ShikokuUniversityKyudoGroupSeeder::class,
        ]);
        $this->call(KyudoResultSeeder::class);

    }
}
