<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_category', function (Blueprint $table) {
            $table->integer('category_id')->autoIncrement();
            $table->string('category_name', 100);
            $table->primary('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_category');
    }
};
