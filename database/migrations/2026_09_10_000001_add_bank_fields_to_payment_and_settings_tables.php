<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('market_payment_settings')) {
            Schema::table('market_payment_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('market_payment_settings', 'bank_code')) {
                    $table->string('bank_code', 20)->default('promptpay')->after('id');
                }
                if (!Schema::hasColumn('market_payment_settings', 'bank_name')) {
                    $table->string('bank_name', 100)->nullable()->after('bank_code');
                }
                if (!Schema::hasColumn('market_payment_settings', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('qr_code_path');
                }
                if (!Schema::hasColumn('market_payment_settings', 'is_default')) {
                    $table->boolean('is_default')->default(false)->after('is_active');
                }
                if (!Schema::hasColumn('market_payment_settings', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('is_default');
                }
            });
        }

        if (Schema::hasTable('payment')) {
            Schema::table('payment', function (Blueprint $table) {
                if (!Schema::hasColumn('payment', 'destination_bank')) {
                    $table->string('destination_bank', 100)->nullable()->after('payment_slip');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('market_payment_settings')) {
            Schema::table('market_payment_settings', function (Blueprint $table) {
                $columns = ['bank_code', 'bank_name', 'is_active', 'is_default', 'sort_order'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('market_payment_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('payment')) {
            Schema::table('payment', function (Blueprint $table) {
                if (Schema::hasColumn('payment', 'destination_bank')) {
                    $table->dropColumn('destination_bank');
                }
            });
        }
    }
};
