<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('item', 'status')) {
            Schema::table('item', function (Blueprint $table) {
                $table->enum('status', ['เปิดขาย', 'ปิดขาย'])
                      ->default('เปิดขาย')
                      ->after('category_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('item', 'status')) {
            Schema::table('item', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
