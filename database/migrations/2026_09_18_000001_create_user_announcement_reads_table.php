<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('announcement_id');
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['user_id', 'announcement_id'], 'user_announcement_unique');
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade');
            $table->foreign('announcement_id')->references('announcement_id')->on('announcement')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_announcement_reads');
    }
};
