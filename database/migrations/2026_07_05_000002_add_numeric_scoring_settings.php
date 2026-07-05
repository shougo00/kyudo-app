<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->json('numeric_score_options')->nullable()->after('grade_colors');
        });

        Schema::table('shots', function (Blueprint $table) {
            $table->integer('numeric_score')->nullable()->after('result');
        });

        Schema::table('match_tate_metas', function (Blueprint $table) {
            $table->string('scoring_mode')->default('hit_miss')->after('elapsed_seconds');
        });

        Schema::table('official_record_sheets', function (Blueprint $table) {
            $table->string('scoring_mode')->default('hit_miss')->after('sheet_no');
        });
    }

    public function down(): void
    {
        Schema::table('official_record_sheets', function (Blueprint $table) {
            $table->dropColumn('scoring_mode');
        });

        Schema::table('match_tate_metas', function (Blueprint $table) {
            $table->dropColumn('scoring_mode');
        });

        Schema::table('shots', function (Blueprint $table) {
            $table->dropColumn('numeric_score');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('numeric_score_options');
        });
    }
};
