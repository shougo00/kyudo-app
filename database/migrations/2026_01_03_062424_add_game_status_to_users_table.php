<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->unsignedInteger('level')
                ->default(1)
                ->after('is_admin')
                ->comment('ユーザーレベル');

            $table->unsignedInteger('exp')
                ->default(0)
                ->comment('現在経験値');

            $table->unsignedInteger('next_exp')
                ->default(100)
                ->comment('次レベル必要経験値');

            $table->unsignedInteger('point')
                ->default(0)
                ->comment('所持ポイント');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['level', 'exp', 'next_exp', 'point']);
        });
    }
};
