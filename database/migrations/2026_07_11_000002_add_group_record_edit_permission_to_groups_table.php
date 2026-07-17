<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'allow_members_edit_group_records')) {
                $table->boolean('allow_members_edit_group_records')
                    ->default(false)
                    ->after('show_group_records_to_members');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'allow_members_edit_group_records')) {
                $table->dropColumn('allow_members_edit_group_records');
            }
        });
    }
};
