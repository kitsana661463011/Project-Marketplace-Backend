<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('citizen_id', 20)->nullable()->after('phone');
            $table->enum('document_status', ['pending', 'approved', 'rejected'])->default('pending')->after('citizen_id');
            $table->dateTime('submission_date')->nullable()->after('document_status');
            $table->string('document_image')->nullable()->after('submission_date');
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['citizen_id', 'document_status', 'submission_date', 'document_image']);
        });
    }
};
