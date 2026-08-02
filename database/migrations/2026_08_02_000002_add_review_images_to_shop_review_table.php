<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_review', function (Blueprint $table) {
            $table->json('review_images')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('shop_review', function (Blueprint $table) {
            $table->dropColumn('review_images');
        });
    }
};
