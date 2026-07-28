<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment', function (Blueprint $table) {
            $table->text('refund_reason')->nullable()->after('status');
            $table->string('refund_bank_name', 100)->nullable()->after('refund_reason');
            $table->string('refund_account_number', 50)->nullable()->after('refund_bank_name');
            $table->string('refund_account_name', 100)->nullable()->after('refund_account_number');
            $table->string('refund_slip', 255)->nullable()->after('refund_account_name');
            $table->dateTime('refunded_at')->nullable()->after('refund_slip');
        });
    }

    public function down(): void
    {
        Schema::table('payment', function (Blueprint $table) {
            $table->dropColumn([
                'refund_reason',
                'refund_bank_name',
                'refund_account_number',
                'refund_account_name',
                'refund_slip',
                'refunded_at',
            ]);
        });
    }
};
