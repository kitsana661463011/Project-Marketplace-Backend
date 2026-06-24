<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement', function (Blueprint $table) {
            $table->integer('announcement_id')->autoIncrement();
            $table->string('title', 100);
            $table->enum('announcement_type', ['urgent', 'activity', 'general'])->default('general');
            $table->text('description')->nullable();
            $table->dateTime('publish_date')->useCurrent();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('user_id');
            $table->primary('announcement_id');
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement');
    }
};
