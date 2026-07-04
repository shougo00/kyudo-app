<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            if (!Schema::hasColumn('records', 'official_sheet_no')) {
                $table->integer('official_sheet_no')->default(1)->after('practice_type');
            }
        });

        if (!Schema::hasTable('official_record_sheets')) {
            Schema::create('official_record_sheets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->integer('sheet_no')->default(1);
                $table->timestamps();

                $table->unique(['group_id', 'date', 'sheet_no'], 'official_record_sheets_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('official_record_sheets');

        Schema::table('records', function (Blueprint $table) {
            if (Schema::hasColumn('records', 'official_sheet_no')) {
                $table->dropColumn('official_sheet_no');
            }
        });
    }
};
