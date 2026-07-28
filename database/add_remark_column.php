<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    if (!Schema::hasColumn('payment', 'remark')) {
        Schema::table('payment', function (Blueprint $table) {
            $table->text('remark')->nullable();
        });
        echo "SUCCESS: Column 'remark' added to 'payment' table.\n";
    } else {
        echo "INFO: Column 'remark' already exists on 'payment' table.\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
