<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_review', function (Blueprint $table) {
            $table->integer('review_id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('shop_id');
            $table->integer('rating');
            $table->text('comment')->nullable();
            $table->dateTime('review_date')->useCurrent();
            $table->enum('status', ['show', 'hidden'])->default('show');
            $table->primary('review_id');
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('shop_id')->references('shop_id')->on('shop')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_review');
    }
};
