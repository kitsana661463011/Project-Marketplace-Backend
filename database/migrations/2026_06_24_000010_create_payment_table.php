<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment', function (Blueprint $table) {
            $table->integer('payment_id')->autoIncrement();
            $table->integer('booking_id');
            $table->decimal('amount', 10, 2);
            $table->dateTime('payment_date')->useCurrent();
            $table->string('payment_slip')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->primary('payment_id');
            $table->foreign('booking_id')->references('booking_id')->on('stall_booking')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};
