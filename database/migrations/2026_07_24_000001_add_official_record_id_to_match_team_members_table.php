<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_team_members', function (Blueprint $table) {
            if (!Schema::hasColumn('match_team_members', 'official_record_id')) {
                $table->foreignId('official_record_id')
                    ->nullable()
                    ->after('position')
                    ->constrained('records')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('match_team_members', function (Blueprint $table) {
            if (Schema::hasColumn('match_team_members', 'official_record_id')) {
                $table->dropConstrainedForeignId('official_record_id');
            }
        });
    }
};
