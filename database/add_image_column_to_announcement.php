<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$exists = Schema::hasColumn('announcement', 'image');
echo "Current image column exists: " . ($exists ? "YES" : "NO") . "\n";

if (!$exists) {
    Schema::table('announcement', function (Blueprint $table) {
        $table->string('image', 255)->nullable()->after('description');
    });
    echo "Added 'image' column to 'announcement' table successfully!\n";
} else {
    echo "'image' column already exists in 'announcement' table.\n";
}
