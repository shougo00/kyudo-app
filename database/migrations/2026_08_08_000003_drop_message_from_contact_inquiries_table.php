<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contact_inquiries') || !Schema::hasColumn('contact_inquiries', 'message')) {
            return;
        }

        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->dropColumn('message');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('contact_inquiries') || Schema::hasColumn('contact_inquiries', 'message')) {
            return;
        }

        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->text('message')->nullable()->after('email');
        });
    }
};
