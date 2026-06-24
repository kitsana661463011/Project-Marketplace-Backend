<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop', function (Blueprint $table) {
            $table->integer('shop_id')->autoIncrement();
            $table->string('shop_name', 100);
            $table->integer('category_id');
            $table->text('description')->nullable();
            $table->string('shop_image')->nullable();
            $table->integer('user_id');
            $table->primary('shop_id');
            $table->foreign('category_id')->references('category_id')->on('shop_category')->onUpdate('cascade');
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop');
    }
};
