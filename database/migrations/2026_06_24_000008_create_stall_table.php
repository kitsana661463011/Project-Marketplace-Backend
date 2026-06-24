<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stall', function (Blueprint $table) {
            $table->integer('stall_id')->autoIncrement();
            $table->string('stall_number', 20);
            $table->string('size', 50)->nullable();
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available');
            $table->integer('zone_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->primary('stall_id');
            $table->foreign('zone_id')->references('zone_id')->on('market_zone')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stall');
    }
};
