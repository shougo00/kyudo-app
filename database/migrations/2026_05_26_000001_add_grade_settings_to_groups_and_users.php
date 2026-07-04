<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'uses_grades')) {
                $table->boolean('uses_grades')->default(false)->after('official_tates_per_page');
            }

            if (!Schema::hasColumn('groups', 'grade_count')) {
                $table->integer('grade_count')->default(3)->after('uses_grades');
            }

            if (!Schema::hasColumn('groups', 'grade_colors')) {
                $table->json('grade_colors')->nullable()->after('grade_count');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'grade_level')) {
                $table->integer('grade_level')->nullable()->after('gender');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'grade_level')) {
                $table->dropColumn('grade_level');
            }
        });

        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'grade_colors')) {
                $table->dropColumn('grade_colors');
            }

            if (Schema::hasColumn('groups', 'grade_count')) {
                $table->dropColumn('grade_count');
            }

            if (Schema::hasColumn('groups', 'uses_grades')) {
                $table->dropColumn('uses_grades');
            }
        });
    }
};
