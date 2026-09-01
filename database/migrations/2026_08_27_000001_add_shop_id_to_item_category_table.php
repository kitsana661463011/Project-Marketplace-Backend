<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_category') && !Schema::hasColumn('item_category', 'shop_id')) {
            Schema::table('item_category', function (Blueprint $table) {
                $table->integer('shop_id')->nullable()->after('category_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('item_category') && Schema::hasColumn('item_category', 'shop_id')) {
            Schema::table('item_category', function (Blueprint $table) {
                $table->dropColumn('shop_id');
            });
        }
    }
};
