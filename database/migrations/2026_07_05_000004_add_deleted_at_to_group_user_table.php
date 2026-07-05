<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_user', function (Blueprint $table) {
            if (!Schema::hasColumn('group_user', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_user', function (Blueprint $table) {
            if (Schema::hasColumn('group_user', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
