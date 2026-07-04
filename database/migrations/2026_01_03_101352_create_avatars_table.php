<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
       Schema::create('avatars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            $table->unsignedBigInteger('hair_id')->nullable();
            $table->unsignedBigInteger('face_id')->nullable();
            $table->unsignedBigInteger('top_id')->nullable();
            $table->unsignedBigInteger('bottom_id')->nullable();
            $table->unsignedBigInteger('shoes_id')->nullable();
            $table->unsignedBigInteger('accessory_id')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::table('avatars', function (Blueprint $table) {
            $table->dropColumn([
                'hair_id',
                'face_id',
                'top_id',
                'bottom_id',
                'shoes_id',
                'accessory_id',
            ]);
        });
    }
};

