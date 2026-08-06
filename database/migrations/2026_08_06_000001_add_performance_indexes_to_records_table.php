<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->index(['user_id', 'date', 'practice_type'], 'records_user_date_type_index');
            $table->index('created_at', 'records_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex('records_user_date_type_index');
            $table->dropIndex('records_created_at_index');
        });
    }
};
