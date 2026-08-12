<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'show_monthly_rank_on_print')) {
                $table->boolean('show_monthly_rank_on_print')
                    ->default(false)
                    ->after('allow_members_edit_group_records');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'show_monthly_rank_on_print')) {
                $table->dropColumn('show_monthly_rank_on_print');
            }
        });
    }
};
