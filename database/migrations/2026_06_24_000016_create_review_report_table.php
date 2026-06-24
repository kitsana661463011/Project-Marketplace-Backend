<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_report', function (Blueprint $table) {
            $table->integer('report_id')->autoIncrement();
            $table->integer('review_id');
            $table->integer('user_id');
            $table->integer('report_count')->default(1);
            $table->text('report_reason')->nullable();
            $table->dateTime('report_date')->useCurrent();
            $table->enum('report_status', ['active', 'inactive'])->default('active');
            $table->primary('report_id');
            $table->foreign('review_id')->references('review_id')->on('shop_review')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('user_id')->on('user')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_report');
    }
};
