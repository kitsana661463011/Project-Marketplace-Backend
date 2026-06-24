<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stall_booking', function (Blueprint $table) {
            $table->integer('booking_id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('stall_id');
            $table->dateTime('booking_date')->useCurrent();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');
            $table->primary('booking_id');
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('stall_id')->references('stall_id')->on('stall')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stall_booking');
    }
};
