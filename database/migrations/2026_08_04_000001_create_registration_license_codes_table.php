<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_license_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('memo')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('registration_license_code_id')
                ->nullable()
                ->after('id')
                ->constrained('registration_license_codes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['registration_license_code_id']);
            $table->dropColumn('registration_license_code_id');
        });

        Schema::dropIfExists('registration_license_codes');
    }
};
