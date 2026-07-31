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
            if (!Schema::hasColumn('stall', 'rental_type')) {
                $table->enum('rental_type', ['daily', 'monthly', 'both'])->default('both')->after('price');
            }
            if (!Schema::hasColumn('stall', 'daily_price')) {
                $table->decimal('daily_price', 10, 2)->default(500.00)->after('rental_type');
            }
            if (!Schema::hasColumn('stall', 'monthly_price')) {
                $table->decimal('monthly_price', 10, 2)->default(5000.00)->after('daily_price');
            }
            if (!Schema::hasColumn('stall', 'entry_fee')) {
                $table->decimal('entry_fee', 10, 2)->default(1000.00)->after('monthly_price');
            }
            if (!Schema::hasColumn('stall', 'security_deposit')) {
                $table->decimal('security_deposit', 10, 2)->default(2000.00)->after('entry_fee');
            }
        });

        Schema::table('stall_booking', function (Blueprint $table) {
            if (!Schema::hasColumn('stall_booking', 'rental_type')) {
                $table->enum('rental_type', ['daily', 'monthly'])->default('daily')->after('stall_id');
            }
            if (!Schema::hasColumn('stall_booking', 'daily_price')) {
                $table->decimal('daily_price', 10, 2)->nullable()->after('rental_type');
            }
            if (!Schema::hasColumn('stall_booking', 'monthly_price')) {
                $table->decimal('monthly_price', 10, 2)->nullable()->after('daily_price');
            }
            if (!Schema::hasColumn('stall_booking', 'entry_fee')) {
                $table->decimal('entry_fee', 10, 2)->nullable()->after('monthly_price');
            }
            if (!Schema::hasColumn('stall_booking', 'security_deposit')) {
                $table->decimal('security_deposit', 10, 2)->nullable()->after('entry_fee');
            }
            if (!Schema::hasColumn('stall_booking', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->nullable()->after('security_deposit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stall', function (Blueprint $table) {
            $table->dropColumn(['rental_type', 'daily_price', 'monthly_price', 'entry_fee', 'security_deposit']);
        });

        Schema::table('stall_booking', function (Blueprint $table) {
            $table->dropColumn(['rental_type', 'daily_price', 'monthly_price', 'entry_fee', 'security_deposit', 'total_amount']);
        });
    }
};
