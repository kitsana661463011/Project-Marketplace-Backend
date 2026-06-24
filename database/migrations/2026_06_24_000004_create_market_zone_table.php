<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_zone', function (Blueprint $table) {
            $table->integer('zone_id')->autoIncrement();
            $table->string('zone_name', 100);
            $table->decimal('zone_price', 10, 2)->default(0.00);
            $table->primary('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_zone');
    }
};
