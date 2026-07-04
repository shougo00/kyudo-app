<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->foreignId('match_team_id')
                ->nullable()
                ->after('practice_type')
                ->constrained('match_teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('match_team_id');
        });
    }
};
