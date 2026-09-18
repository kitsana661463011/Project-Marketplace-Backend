<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement') && !Schema::hasColumn('announcement', 'end_date')) {
            Schema::table('announcement', function (Blueprint $table) {
                $table->dateTime('end_date')->nullable()->after('publish_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('announcement') && Schema::hasColumn('announcement', 'end_date')) {
            Schema::table('announcement', function (Blueprint $table) {
                $table->dropColumn('end_date');
            });
        }
    }
};
