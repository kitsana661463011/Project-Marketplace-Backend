<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\Illuminate\Support\Facades\DB::statement("UPDATE notification SET message = REPLACE(message, 'คำขอเปิดร้านค้า', 'คำขอสมัครเป็นผู้ค้า')");

$rows = \Illuminate\Support\Facades\DB::table('notification')->latest('notification_id')->take(10)->get();
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
