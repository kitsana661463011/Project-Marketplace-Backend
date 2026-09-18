<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$exists = Schema::hasColumn('announcement', 'end_date');
echo "Current end_date column exists: " . ($exists ? "YES" : "NO") . "\n";

if (!$exists) {
    Schema::table('announcement', function (Blueprint $table) {
        $table->dateTime('end_date')->nullable()->after('publish_date');
    });
    echo "Added 'end_date' column to 'announcement' table successfully!\n";
} else {
    echo "'end_date' column already exists in 'announcement' table.\n";
}
