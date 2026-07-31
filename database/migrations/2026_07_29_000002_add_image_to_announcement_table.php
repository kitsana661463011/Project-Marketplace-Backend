<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement') && !Schema::hasColumn('announcement', 'image')) {
            Schema::table('announcement', function (Blueprint $table) {
                $table->string('image', 255)->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('announcement') && Schema::hasColumn('announcement', 'image')) {
            Schema::table('announcement', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
