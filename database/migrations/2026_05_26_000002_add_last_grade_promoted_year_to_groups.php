<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'last_grade_promoted_year')) {
                $table->integer('last_grade_promoted_year')->nullable()->after('grade_colors');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'last_grade_promoted_year')) {
                $table->dropColumn('last_grade_promoted_year');
            }
        });
    }
};
