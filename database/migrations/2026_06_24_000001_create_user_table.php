<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('username', 100);
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->string('phone', 15)->nullable();
            $table->string('profile_image')->nullable();
            $table->enum('role', ['buyer', 'seller', 'admin']);
            $table->dateTime('created_at')->useCurrent();
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user');
    }
};
