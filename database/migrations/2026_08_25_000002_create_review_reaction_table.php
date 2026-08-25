<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reaction', function (Blueprint $table) {
            $table->integer('reaction_id')->autoIncrement();
            $table->integer('review_id');
            $table->integer('user_id');
            $table->enum('reaction_type', ['like', 'dislike']);
            $table->timestamps();
            $table->primary('reaction_id');

            $table->unique(['review_id', 'user_id']);
            $table->foreign('review_id')->references('review_id')->on('shop_review')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reaction');
    }
};
