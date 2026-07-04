<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->string('division')->default('male');
            $table->integer('tate_size')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_teams');
    }
};
