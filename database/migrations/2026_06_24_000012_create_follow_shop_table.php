<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_shop', function (Blueprint $table) {
            $table->integer('follow_id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('shop_id');
            $table->dateTime('follow_date')->useCurrent();
            $table->primary('follow_id');
            $table->unique(['user_id', 'shop_id']);
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('shop_id')->references('shop_id')->on('shop')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_shop');
    }
};
