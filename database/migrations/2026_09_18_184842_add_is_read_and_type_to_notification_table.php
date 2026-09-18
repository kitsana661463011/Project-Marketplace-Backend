<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification', function (Blueprint $table) {
            if (!Schema::hasColumn('notification', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('notify_date');
            }
            if (!Schema::hasColumn('notification', 'type')) {
                $table->string('type', 50)->default('other')->after('is_read');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification', function (Blueprint $table) {
            if (Schema::hasColumn('notification', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('notification', 'is_read')) {
                $table->dropColumn('is_read');
            }
        });
    }
};
