<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_map_items', function (Blueprint $table) {
            $table->integer('map_item_id')->autoIncrement();
            $table->integer('map_id');
            $table->enum('item_type', ['block', 'road', 'zone', 'entrance', 'toilet']);
            $table->integer('stall_id')->nullable();
            $table->integer('zone_id')->nullable();
            $table->string('label', 100)->nullable();
            $table->integer('x');
            $table->integer('y');
            $table->integer('width');
            $table->integer('height');
            $table->string('fill_color', 20)->default('#5d8aff');
            $table->integer('rotation')->default(0);
            $table->integer('z_index')->default(0);
            $table->timestamps();
            $table->primary('map_item_id');
            $table->foreign('map_id')->references('map_id')->on('market_maps')->onDelete('cascade');
            $table->foreign('stall_id')->references('stall_id')->on('stall')->onDelete('set null');
            $table->foreign('zone_id')->references('zone_id')->on('market_zone')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_map_items');
    }
};
