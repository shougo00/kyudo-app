<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_team_members', function (Blueprint $table) {
            if (!Schema::hasColumn('match_team_members', 'is_absent')) {
                $table->boolean('is_absent')->default(false)->after('position');
            }

            if (!Schema::hasColumn('match_team_members', 'is_late')) {
                $table->boolean('is_late')->default(false)->after('is_absent');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE match_team_members MODIFY position INT NULL');
        }
    }

    public function down(): void
    {
        DB::table('match_team_members')
            ->whereNull('position')
            ->delete();

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE match_team_members MODIFY position INT NOT NULL');
        }

        Schema::table('match_team_members', function (Blueprint $table) {
            if (Schema::hasColumn('match_team_members', 'is_late')) {
                $table->dropColumn('is_late');
            }

            if (Schema::hasColumn('match_team_members', 'is_absent')) {
                $table->dropColumn('is_absent');
            }
        });
    }
};
