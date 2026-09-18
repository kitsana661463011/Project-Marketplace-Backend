<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StallBooking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

$missingBookings = StallBooking::where('status', 'approved')->doesntHave('payment')->get();
echo "Found " . $missingBookings->count() . " approved bookings without payment record.\n";

foreach ($missingBookings as $b) {
    $amount = (float)($b->total_amount ?? 0);
    if ($amount <= 0) {
        if ($b->rental_type === 'monthly') {
            $amount = (float)($b->monthly_price ?? 0) + (float)($b->entry_fee ?? 0) + (float)($b->security_deposit ?? 0);
        } else {
            $amount = (float)($b->daily_price ?? 0);
        }
    }
    
    $paymentDate = $b->booking_date ?? $b->start_date ?? now();

    Payment::create([
        'booking_id' => $b->booking_id,
        'amount' => $amount,
        'payment_date' => $paymentDate,
        'payment_slip' => null,
        'status' => 'verified',
        'remark' => 'สร้างอัตโนมัติจากการอนุมัติการจอง',
    ]);
    echo "Created verified payment for Booking #{$b->booking_id} (Amount: {$amount}, Date: {$paymentDate})\n";
}

echo "\n--- Summary ---\n";
echo "Total Bookings: " . StallBooking::count() . " (Approved: " . StallBooking::where('status', 'approved')->count() . ")\n";
echo "Total Payments: " . Payment::count() . " (Verified: " . Payment::where('status', 'verified')->count() . ", Refunded: " . Payment::where('status', 'refunded')->count() . ")\n";
