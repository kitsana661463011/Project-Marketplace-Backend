<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stall', function (Blueprint $table) {
            if (!Schema::hasColumn('stall', 'has_electricity')) {
                $table->boolean('has_electricity')->default(true)->after('security_deposit');
            }
            if (!Schema::hasColumn('stall', 'has_water')) {
                $table->boolean('has_water')->default(true)->after('has_electricity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stall', function (Blueprint $table) {
            if (Schema::hasColumn('stall', 'has_water')) {
                $table->dropColumn('has_water');
            }
            if (Schema::hasColumn('stall', 'has_electricity')) {
                $table->dropColumn('has_electricity');
            }
        });
    }
};
