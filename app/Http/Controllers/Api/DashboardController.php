<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProblemReport;
use App\Models\Stall;
use App\Models\StallBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function overview()
    {
        $totalStalls = (int) Stall::count();
        $occupiedStalls = (int) Stall::where('status', 'occupied')->count();
        $availableStalls = (int) Stall::where('status', 'available')->count();
        $pendingBookings = (int) StallBooking::where('status', 'pending')->count();
        $pendingReports = (int) ProblemReport::where('status', 'pending')->count();
        $totalSellers = (int) DB::table('user')->where('role', 'seller')->where('document_status', 'approved')->count();
        $pendingSellers = (int) DB::table('user')->where('document_status', 'pending')->where(function ($q) {
            $q->where('role', 'buyer')->orWhere('role', 'seller');
        })->count();

        $totalShopsCount = (int) DB::table('shop')->count();
        $categoryShare = DB::table('shop_category as sc')
            ->leftJoin('shop as s', 'sc.category_id', '=', 's.category_id')
            ->select('sc.category_id as id', 'sc.category_name as name', DB::raw('COUNT(s.shop_id) as count'))
            ->groupBy('sc.category_id', 'sc.category_name')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) use ($totalShopsCount) {
                $c = (int) $item->count;
                $item->count = $c;
                $item->percentage = $totalShopsCount > 0 ? round(($c / $totalShopsCount) * 100, 1) : 0;
                return $item;
            });

        $monthlyBookings = DB::table('stall_booking')
            ->select(
                DB::raw("DATE_FORMAT(booking_date, '%b') as month"),
                DB::raw('COUNT(booking_id) as count')
            )
            ->groupBy(DB::raw("DATE_FORMAT(booking_date, '%b')"))
            ->orderBy(DB::raw("MIN(booking_date)"))
            ->get();

        $bookings = StallBooking::with(['user', 'stall'])
            ->orderByDesc('booking_id')
            ->take(3)
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => 'booking-' . $booking->booking_id,
                    'type' => 'booking',
                    'title' => 'การจองแผง ' . ($booking->stall ? $booking->stall->stall_number : 'ไม่ระบุ'),
                    'description' => 'ผู้ขอจอง: ' . ($booking->user ? $booking->user->username : 'ไม่ระบุ'),
                    'timestamp' => $booking->booking_date,
                    'status' => $booking->status,
                ];
            });

        $shops = DB::table('shop')
            ->orderByDesc('shop_id')
            ->take(3)
            ->get()
            ->map(function ($shop) {
                return [
                    'id' => 'shop-' . $shop->shop_id,
                    'type' => 'shop',
                    'title' => 'เปิดร้านค้าใหม่: ' . $shop->shop_name,
                    'description' => 'รายละเอียด: ' . ($shop->description ?: 'ไม่มีรายละเอียด'),
                    'timestamp' => now()->toDateTimeString(),
                    'status' => 'approved',
                ];
            });

        $recentActivity = $bookings->merge($shops)
            ->sortByDesc(function ($item) {
                return $item['timestamp'];
            })
            ->take(3)
            ->values();

        $totalRevenue = (float) DB::table('payment')->where('status', 'verified')->sum('amount');

        $zoneSummary = DB::table('market_zone as mz')
            ->leftJoin('stall as st', 'mz.zone_id', '=', 'st.zone_id')
            ->select(
                'mz.zone_id',
                'mz.zone_name',
                DB::raw('COUNT(st.stall_id) as total_stalls'),
                DB::raw("COUNT(CASE WHEN st.status = 'occupied' THEN 1 END) as occupied_count"),
                DB::raw("COUNT(CASE WHEN st.status = 'available' THEN 1 END) as available_count")
            )
            ->groupBy('mz.zone_id', 'mz.zone_name')
            ->get();

        $userInterestsFormatted = [];
        $totalUsersWithInterests = 0;
        try {
            if (Schema::hasTable('user_interest_option')) {
                $allInterestsOptions = DB::table('user_interest_option')->pluck('interest_name', 'interest_id')->toArray();
                $interestCounts = [];
                foreach ($allInterestsOptions as $name) {
                    $interestCounts[$name] = 0;
                }

                if (Schema::hasTable('user_has_interest')) {
                    $userInterestsRaw = DB::table('user_has_interest as uhi')
                        ->join('user_interest_option as uio', 'uhi.interest_id', '=', 'uio.interest_id')
                        ->select('uio.interest_name', DB::raw('COUNT(uhi.user_id) as count'))
                        ->groupBy('uio.interest_id', 'uio.interest_name')
                        ->get();

                    foreach ($userInterestsRaw as $row) {
                        $interestCounts[$row->interest_name] = (int)$row->count;
                    }
                } elseif (Schema::hasColumn('user', 'interests')) {
                    $userInterestsRaw = DB::table('user')->whereNotNull('interests')->pluck('interests')->toArray();
                    foreach ($userInterestsRaw as $userIntStr) {
                        if (!empty($userIntStr)) {
                            $tags = array_map('trim', explode(',', $userIntStr));
                            foreach ($tags as $tag) {
                                if (!empty($tag) && isset($interestCounts[$tag])) {
                                    $interestCounts[$tag]++;
                                }
                            }
                        }
                    }
                }

                arsort($interestCounts);
                $totalSelections = array_sum($interestCounts);
                $interestNameToId = array_flip($allInterestsOptions);
                foreach ($interestCounts as $name => $count) {
                    $userInterestsFormatted[] = [
                        'id' => $interestNameToId[$name] ?? null,
                        'name' => $name,
                        'count' => $count,
                        'percentage' => $totalSelections > 0 ? round(($count / $totalSelections) * 100, 1) : 0,
                    ];
                }
            }
        } catch (\Exception $e) {
            $userInterestsFormatted = [];
        }

        return response()->json([
            'status' => true,
            'message' => 'Dashboard overview retrieved successfully',
            'data' => [
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_stalls' => $totalStalls,
                    'occupied_stalls' => $occupiedStalls,
                    'available_stalls' => $availableStalls,
                    'pending_bookings' => $pendingBookings,
                    'pending_reports' => $pendingReports,
                    'total_sellers' => $totalSellers,
                    'pending_sellers' => $pendingSellers,
                    'total_users_with_interests' => $totalUsersWithInterests,
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
                'user_interests' => $userInterestsFormatted,
                'zone_summary' => $zoneSummary,
                'recent_activity' => $recentActivity,
            ],
        ], 200);
    }

    public function getCategories()
    {
        $categories = DB::table('shop_category')->orderBy('category_id')->get();

        return response()->json([
            'status' => true,
            'data' => $categories,
        ], 200);
    }

    public function storeCategory(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $name = trim($request->input('category_name'));
        $desc = trim((string) $request->input('description'));

        $exists = DB::table('shop_category')->where('category_name', $name)->first();
        if (! $exists) {
            DB::table('shop_category')->insert([
                'category_name' => $name,
                'description' => $desc,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Category added successfully',
        ], 201);
    }

    public function getUserInterests()
    {
        $options = DB::table('user_interest_option')->orderBy('interest_id')->get();

        return response()->json([
            'status' => true,
            'data' => $options,
        ], 200);
    }

    public function storeUserInterest(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'interest_name' => 'required|string|max:100',
        ]);

        $name = trim($request->input('interest_name'));

        $exists = DB::table('user_interest_option')->where('interest_name', $name)->exists();
        if (! $exists) {
            DB::table('user_interest_option')->insert([
                'interest_name' => $name,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'User interest option added successfully',
        ], 201);
    }

    public function destroyCategory($id)
    {
        $category = DB::table('shop_category')->where('category_id', $id)->first();
        if (! $category) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่พบหมวดหมู่สินค้านี้',
            ], 404);
        }

        $shopUsedCount = DB::table('shop')->where('category_id', $id)->count();
        if ($shopUsedCount > 0) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่สามารถลบหมวดหมู่นี้ได้ เนื่องจากมีร้านค้าใช้งานอยู่',
            ], 400);
        }

        DB::table('shop_category')->where('category_id', $id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'ลบหมวดหมู่สินค้าสำเร็จแล้ว',
        ], 200);
    }

    public function destroyUserInterest($id)
    {
        $option = DB::table('user_interest_option')->where('interest_id', $id)->first();
        if (! $option) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่พบทตัวเลือกความสนใจนี้',
            ], 404);
        }

        if (Schema::hasTable('user_has_interest')) {
            DB::table('user_has_interest')->where('interest_id', $id)->delete();
        }

        DB::table('user_interest_option')->where('interest_id', $id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'ลบตัวเลือกความสนใจสำเร็จแล้ว',
        ], 200);
    }

    public function badgeCounts()
    {
        $pendingBookings = (int) StallBooking::where('status', 'pending')->count();
        $pendingReports  = (int) ProblemReport::where('status', 'pending')->count();
        $pendingSellers  = (int) DB::table('user')->where('document_status', 'pending')->count();

        return response()->json([
            'status' => true,
            'data'   => [
                'verifications' => $pendingBookings,
                'reports'       => $pendingReports,
                'sellers'       => $pendingSellers,
            ],
        ], 200);
    }
}
