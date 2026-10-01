<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification', function (Blueprint $table) {
            if (!Schema::hasColumn('notification', 'title')) {
                $table->string('title', 150)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('notification', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification', function (Blueprint $table) {
            if (Schema::hasColumn('notification', 'reference_id')) {
                $table->dropColumn('reference_id');
            }
            if (Schema::hasColumn('notification', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};
