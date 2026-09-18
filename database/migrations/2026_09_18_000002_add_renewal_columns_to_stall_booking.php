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
        Schema::table('stall_booking', function (Blueprint $table) {
            if (!Schema::hasColumn('stall_booking', 'renewal_end_date')) {
                $table->date('renewal_end_date')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('stall_booking', 'renewal_amount')) {
                $table->decimal('renewal_amount', 10, 2)->nullable()->after('renewal_end_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stall_booking', function (Blueprint $table) {
            if (Schema::hasColumn('stall_booking', 'renewal_end_date')) {
                $table->dropColumn('renewal_end_date');
            }
            if (Schema::hasColumn('stall_booking', 'renewal_amount')) {
                $table->dropColumn('renewal_amount');
            }
        });
    }
};
