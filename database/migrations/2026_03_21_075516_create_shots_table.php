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
        Schema::create('shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained()->cascadeOnDelete();
            $table->integer('shot_no'); // 1〜4
            $table->string('result')->nullable(); // hit / miss / null
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shots');
    }
};
