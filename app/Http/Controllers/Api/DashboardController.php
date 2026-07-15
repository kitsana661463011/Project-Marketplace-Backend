<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProblemReport;
use App\Models\Stall;
use App\Models\StallBooking;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function overview()
    {
        $totalStalls = (int) Stall::count();
        $occupiedStalls = (int) Stall::where('status', 'occupied')->count();
        $availableStalls = (int) Stall::where('status', 'available')->count();
        $pendingBookings = (int) StallBooking::where('status', 'pending')->count();
        $pendingReports = (int) ProblemReport::where('status', 'pending')->count();

        $categoryShare = DB::table('shop as s')
            ->join('shop_category as sc', 's.category_id', '=', 'sc.category_id')
            ->select('s.category_id as id', 'sc.category_name as name', DB::raw('COUNT(*) as count'))
            ->groupBy('s.category_id', 'sc.category_name')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) use ($totalStalls) {
                $item->percentage = $item->count > 0 ? round(($item->count / max($totalStalls, 1)) * 100, 1) : 0;

                return $item;
            });

        $bookings = DB::table('stall_booking as sb')
            ->join('user as u', 'sb.user_id', '=', 'u.user_id')
            ->join('stall as st', 'sb.stall_id', '=', 'st.stall_id')
            ->select(
                'sb.booking_id as id',
                DB::raw("'booking' as type"),
                DB::raw("CONCAT('คำขอจองบูธ ', st.stall_number) as title"),
                DB::raw("CONCAT(u.username, ' / ', st.stall_number) as owner"),
                'sb.status',
                'sb.booking_date as created_at',
                DB::raw("CASE WHEN sb.status = 'approved' THEN 'สำเร็จ (Approved)' WHEN sb.status = 'pending' THEN 'รออนุมัติ (Pending)' ELSE 'ยกเลิก (Cancelled)' END as status_label"),
                DB::raw("CONCAT('คำขอจอง ', st.stall_number, ' โดย ', u.username) as message")
            )
            ->orderByDesc('sb.booking_date')
            ->limit(3)
            ->get();

        $shops = DB::table('shop as s')
            ->join('user as u', 's.user_id', '=', 'u.user_id')
            ->select(
                's.shop_id as id',
                DB::raw("'shop' as type"),
                DB::raw("CONCAT('ร้าน ', s.shop_name) as title"),
                DB::raw("CONCAT(u.username, ' / ', s.shop_name) as owner"),
                DB::raw("'approved' as status"),
                DB::raw('COALESCE(u.created_at, NOW()) as created_at'),
                DB::raw("'สำเร็จ (Approved)' as status_label"),
                DB::raw("CONCAT('ร้าน ', s.shop_name, ' สมัครเข้ามาใหม่') as message")
            )
            ->orderByDesc('u.created_at')
            ->limit(3)
            ->get();

        $recentActivity = $bookings->merge($shops)
            ->sortByDesc(function ($item) {
                return $item->created_at;
            })
            ->take(3)
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard overview retrieved successfully',
            'data' => [
                'summary' => [
                    'total_stalls' => $totalStalls,
                    'occupied_stalls' => $occupiedStalls,
                    'available_stalls' => $availableStalls,
                    'pending_bookings' => $pendingBookings,
                    'pending_reports' => $pendingReports,
                ],
                'overview_cards' => [
                    [
                        'title' => 'แจ้งเตือนเหตุ',
                        'value' => $pendingReports,
                        'subValue' => '',
                        'detail' => 'ต้องตรวจสอบทันที',
                        'type' => 'report',
                    ],
                    [
                        'title' => 'คำขอจองที่รออนุมัติ',
                        'value' => $pendingBookings,
                        'subValue' => '',
                        'detail' => 'รอการตรวจสอบจากแอดมิน',
                        'type' => 'booking',
                    ],
                    [
                        'title' => 'ล็อกที่มีคนจอง',
                        'value' => $occupiedStalls,
                        'subValue' => '/'.$totalStalls,
                        'detail' => '',
                        'type' => 'occupied',
                    ],
                    [
                        'title' => 'ล็อกที่ว่าง',
                        'value' => $availableStalls,
                        'subValue' => '',
                        'detail' => 'พร้อมเปิดให้จอง',
                        'type' => 'available',
                    ],
                ],
                'category_share' => $categoryShare,
                'recent_activity' => $recentActivity,
            ],
        ], 200);
    }

    public function badgeCounts()
    {
        $pendingBookings = (int) StallBooking::where('status', 'pending')->count();
        $pendingReports  = (int) ProblemReport::where('status', 'pending')->count();

        return response()->json([
            'status' => true,
            'data'   => [
                'verifications' => $pendingBookings,
                'reports'       => $pendingReports,
            ],
        ], 200);
    }
}
