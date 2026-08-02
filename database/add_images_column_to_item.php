<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$exists = Schema::hasColumn('item', 'images');
echo "Current images column exists: " . ($exists ? "YES" : "NO") . "\n";

if (!$exists) {
    Schema::table('item', function (Blueprint $table) {
        $table->text('images')->nullable()->after('item_image');
    });
    echo "Added 'images' column to 'item' table successfully!\n";
} else {
    echo "'images' column already exists in 'item' table.\n";
}
