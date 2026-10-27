<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shot_change_logs', function (Blueprint $table) {
            $table->id();
            // Keep IDs and names as snapshots, even if the original data is deleted.
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('group_name')->nullable();
            $table->unsignedBigInteger('record_id');
            $table->unsignedBigInteger('shot_id');
            $table->unsignedBigInteger('actor_user_id');
            $table->string('actor_name');
            $table->string('actor_username');
            $table->unsignedBigInteger('subject_user_id');
            $table->string('subject_name');
            $table->string('subject_username');
            $table->date('practice_date');
            $table->unsignedInteger('official_sheet_no');
            $table->unsignedInteger('tate_no');
            $table->unsignedTinyInteger('shot_no');
            $table->string('old_result')->nullable();
            $table->string('new_result')->nullable();
            $table->integer('old_numeric_score')->nullable();
            $table->integer('new_numeric_score')->nullable();
            $table->string('source', 32);
            $table->dateTime('created_at', 6)->index();

            $table->index(['group_id', 'practice_date']);
            $table->index(['practice_date', 'tate_no', 'shot_no']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['subject_user_id', 'practice_date']);
            $table->index(['shot_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shot_change_logs');
    }
};
