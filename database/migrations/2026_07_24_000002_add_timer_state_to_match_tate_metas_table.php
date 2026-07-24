<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_tate_metas', function (Blueprint $table) {
            if (!Schema::hasColumn('match_tate_metas', 'is_timer_running')) {
                $table->boolean('is_timer_running')->default(false)->after('elapsed_seconds');
            }

            if (!Schema::hasColumn('match_tate_metas', 'timer_started_at')) {
                $table->timestamp('timer_started_at')->nullable()->after('is_timer_running');
            }
        });
    }

    public function down(): void
    {
        Schema::table('match_tate_metas', function (Blueprint $table) {
            if (Schema::hasColumn('match_tate_metas', 'timer_started_at')) {
                $table->dropColumn('timer_started_at');
            }

            if (Schema::hasColumn('match_tate_metas', 'is_timer_running')) {
                $table->dropColumn('is_timer_running');
            }
        });
    }
};
