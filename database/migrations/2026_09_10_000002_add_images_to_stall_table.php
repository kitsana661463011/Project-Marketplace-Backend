<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stall')) {
            Schema::table('stall', function (Blueprint $table) {
                if (!Schema::hasColumn('stall', 'image1')) {
                    $table->string('image1', 255)->nullable()->after('has_water');
                }
                if (!Schema::hasColumn('stall', 'image2')) {
                    $table->string('image2', 255)->nullable()->after('image1');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stall')) {
            Schema::table('stall', function (Blueprint $table) {
                if (Schema::hasColumn('stall', 'image2')) {
                    $table->dropColumn('image2');
                }
                if (Schema::hasColumn('stall', 'image1')) {
                    $table->dropColumn('image1');
                }
            });
        }
    }
};
