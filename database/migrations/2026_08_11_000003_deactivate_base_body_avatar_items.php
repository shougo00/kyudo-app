<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('items') || !Schema::hasColumn('items', 'is_active')) {
            return;
        }

        DB::table('items')
            ->where('image_path', 'like', 'body/%')
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('items') || !Schema::hasColumn('items', 'is_active')) {
            return;
        }

        DB::table('items')
            ->where('image_path', 'like', 'body/%')
            ->update(['is_active' => true]);
    }
};
