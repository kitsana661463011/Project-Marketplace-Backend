<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$bookings = App\Models\StallBooking::with('payment', 'user', 'stall')->get();
foreach ($bookings as $b) {
    $hasP = $b->payment ? ('YES (id:' . $b->payment->payment_id . ', status:' . $b->payment->status . ', amt:' . $b->payment->amount . ')') : 'NO PAYMENT RECORD';
    $uName = $b->user ? ($b->user->username ?? $b->user->name ?? $b->user->email) : 'N/A';
    $sNo = $b->stall ? $b->stall->stall_number : 'N/A';
    echo "Booking #{$b->booking_id} | User: {$uName} | Stall: {$sNo} | Status: {$b->status} | TotalAmt: {$b->total_amount} | Payment: {$hasP}\n";
}
