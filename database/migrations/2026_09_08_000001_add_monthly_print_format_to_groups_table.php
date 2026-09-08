<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'monthly_print_format')) {
                $table->string('monthly_print_format')
                    ->default('combined')
                    ->after('show_monthly_rank_on_print');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'monthly_print_format')) {
                $table->dropColumn('monthly_print_format');
            }
        });
    }
};
