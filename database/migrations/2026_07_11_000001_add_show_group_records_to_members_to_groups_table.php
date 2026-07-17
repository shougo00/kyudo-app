<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'show_group_records_to_members')) {
                $table->boolean('show_group_records_to_members')
                    ->default(false)
                    ->after('official_tates_per_page');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'show_group_records_to_members')) {
                $table->dropColumn('show_group_records_to_members');
            }
        });
    }
};
