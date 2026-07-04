<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('lineups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')->constrained()->cascadeOnDelete();

            $table->date('date'); // 日ごと管理

            $table->integer('tate_size')->default(3); // 何人立

            $table->timestamps();

            // 同じグループ×日付は1つだけ
            $table->unique(['group_id','date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lineups');
    }
};
