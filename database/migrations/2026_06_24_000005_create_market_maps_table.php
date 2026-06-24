<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_maps', function (Blueprint $table) {
            $table->integer('map_id')->autoIncrement();
            $table->string('map_name', 100);
            $table->integer('map_width')->default(5000);
            $table->integer('map_height')->default(5000);
            $table->timestamps();
            $table->primary('map_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_maps');
    }
};
