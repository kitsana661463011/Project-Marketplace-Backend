<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment', function (Blueprint $table) {
            if (!Schema::hasColumn('payment', 'remark')) {
                $table->text('remark')->nullable()->after('refunded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment', function (Blueprint $table) {
            if (Schema::hasColumn('payment', 'remark')) {
                $table->dropColumn('remark');
            }
        });
    }
};
