<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alter rating column to decimal(3, 1) to support half stars (e.g. 4.5, 3.5)
        Schema::table('shop_review', function (Blueprint $table) {
            $table->decimal('rating', 3, 1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shop_review', function (Blueprint $table) {
            $table->integer('rating')->change();
        });
    }
};
