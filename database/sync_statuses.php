<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Update stall_booking status to match payment status for refund_requested and refunded
DB::table('stall_booking as sb')
    ->join('payment as p', 'p.booking_id', '=', 'sb.booking_id')
    ->whereIn('p.status', ['refund_requested', 'refunded'])
    ->update([
        'sb.status' => DB::raw('p.status')
    ]);

echo "Database status sync completed successfully!\n";

$bookings = DB::table('stall_booking as sb')
    ->leftJoin('payment as p', 'p.booking_id', '=', 'sb.booking_id')
    ->leftJoin('user as u', 'u.user_id', '=', 'sb.user_id')
    ->select('sb.booking_id', 'u.username', 'sb.status as booking_status', 'p.status as payment_status')
    ->get();

echo json_encode($bookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
