<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_report', function (Blueprint $table) {
            $table->integer('problem_id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('stall_id');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->dateTime('report_date')->useCurrent();
            $table->enum('status', ['pending', 'progress', 'resolved'])->default('pending');
            $table->primary('problem_id');
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('stall_id')->references('stall_id')->on('stall')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_report');
    }
};
