<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_tate_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_team_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('tate_no');
            $table->integer('elapsed_seconds')->default(0);
            $table->timestamps();

            $table->unique(['match_team_id', 'date', 'tate_no'], 'match_tate_meta_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_tate_metas');
    }
};
