<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('tate_no');
            $table->integer('position');
            $table->timestamps();

            $table->unique(['match_team_id', 'tate_no', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_team_members');
    }
};
