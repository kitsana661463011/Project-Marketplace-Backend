<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:check-expiring', function () {
    $today = now()->startOfDay();
    $threeDaysLater = now()->addDays(3)->endOfDay();

    $expiringBookings = \Illuminate\Support\Facades\DB::table('stall_booking as sb')
        ->join('stall as s', 's.stall_id', '=', 'sb.stall_id')
        ->select('sb.booking_id', 'sb.user_id', 'sb.end_date', 's.stall_number')
        ->where('sb.status', 'approved')
        ->whereBetween('sb.end_date', [$today->format('Y-m-d'), $threeDaysLater->format('Y-m-d')])
        ->get();

    $count = 0;
    foreach ($expiringBookings as $b) {
        $endDate = \Carbon\Carbon::parse($b->end_date)->startOfDay();
        $daysLeft = (int) $today->diffInDays($endDate, false);

        // Check if notification was already sent today for this booking
        $alreadySentToday = \App\Models\Notification::where('user_id', $b->user_id)
            ->where('reference_id', $b->booking_id)
            ->where('type', 'booking')
            ->whereDate('notify_date', now()->toDateString())
            ->exists();

        if (! $alreadySentToday) {
            $msg = $daysLeft == 0
                ? "⚠️ สัญญาเช่าแผงค้า {$b->stall_number} หมดอายุวันนี้! กรุณากดต่อสัญญาเพื่อรักษาสิทธิ์แผงค้า"
                : "⚠️ สัญญาเช่าแผงค้า {$b->stall_number} จะหมดอายุในอีก {$daysLeft} วัน กรุณากดต่อสัญญาล่วงหน้า";

            \App\Models\Notification::create([
                'user_id' => $b->user_id,
                'title' => 'สัญญาแผงค้าใกล้หมดอายุ',
                'message' => $msg,
                'notify_date' => now(),
                'type' => 'booking',
                'reference_id' => $b->booking_id,
                'is_read' => false,
            ]);
            $count++;
        }
    }

    $this->info("Checked expiring bookings: {$count} notification(s) created.");
})->purpose('Check stalls expiring within 3 days and notify users');

\Illuminate\Support\Facades\Schedule::command('bookings:check-expiring')->dailyAt('08:30');

