<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'username')) {
            return;
        }

        $now = now();

        DB::table('users')->updateOrInsert(
            ['username' => 'KANRI'],
            [
                'name' => 'システム管理者',
                'email' => null,
                'password' => Hash::make('KANRI'),
                'is_admin' => true,
                'gender' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'username')) {
            return;
        }

        DB::table('users')->where('username', 'KANRI')->delete();
    }
};
