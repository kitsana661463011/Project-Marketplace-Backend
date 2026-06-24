<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item', function (Blueprint $table) {
            $table->integer('item_id')->autoIncrement();
            $table->integer('shop_id');
            $table->string('item_name', 100);
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->string('item_image')->nullable();
            $table->integer('category_id');
            $table->primary('item_id');
            $table->foreign('shop_id')->references('shop_id')->on('shop')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('category_id')->references('category_id')->on('item_category')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item');
    }
};
