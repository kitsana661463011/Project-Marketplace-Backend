<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReviewReport;
use App\Models\ShopReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewReportController extends Controller
{
    public function index(Request $request)
    {
        $query = ReviewReport::with(['user', 'review.user', 'review.shop']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('report_reason', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('username', 'like', "%{$search}%");
                    })
                    ->orWhereHas('review', function ($rq) use ($search) {
                        $rq->where('comment', 'like', "%{$search}%")
                            ->orWhereHas('shop', function ($sq) use ($search) {
                                $sq->where('shop_name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            if ($status === 'pending') {
                $query->where('report_status', 'active');
            } elseif ($status === 'progress') {
                $query->where('report_status', 'progress');
            } elseif ($status === 'resolved') {
                $query->whereIn('report_status', ['resolved', 'inactive', 'dismissed']);
            } else {
                $query->where('report_status', $status);
            }
        }

        if ($request->filled('start_date')) {
            $query->whereDate('report_date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('report_date', '<=', $request->input('end_date'));
        }

        $reports = $query->orderByDesc('report_date')->get()->map(function (ReviewReport $report) {
            $firstImage = null;
            $images = $report->review?->review_images;
            if (is_array($images) && count($images) > 0) {
                $firstImage = $images[0];
            } elseif (is_string($images) && !empty($images)) {
                $decoded = json_decode($images, true);
                $firstImage = is_array($decoded) && count($decoded) > 0 ? $decoded[0] : $images;
            }

            $rawStatus = $report->report_status ?: 'active';
            $mappedStatus = match ($rawStatus) {
                'active' => 'pending',
                'progress' => 'progress',
                'resolved', 'inactive', 'dismissed' => 'resolved',
                default => 'pending',
            };

            return [
                'id' => 'rev_' . $report->report_id,
                'problem_id' => 'rev_' . $report->report_id,
                'review_report_id' => $report->report_id,
                'is_review_report' => true,
                'description' => '[รายงานความคิดเห็น: ' . ($report->review?->shop?->shop_name ?? 'ร้านค้า') . '] ' . $report->report_reason . ' | ข้อความ: "' . ($report->review?->comment ?? '-') . '"',
                'image' => $firstImage,
                'report_date' => $report->report_date,
                'status' => $mappedStatus,
                'report_status_raw' => $rawStatus,
                'report_type' => 'feedback',
                'admin_note' => $report->admin_note,
                'admin_comment' => $report->admin_note,
                'user_id' => $report->user_id,
                'user_name' => $report->user?->username ?? 'ไม่ระบุ',
                'stall_id' => null,
                'stall_number' => $report->review?->shop ? 'ร้าน ' . $report->review->shop->shop_name : 'รีวิวร้านค้า',
                'review_details' => [
                    'report_id' => $report->report_id,
                    'review_id' => $report->review_id,
                    'report_reason' => $report->report_reason,
                    'report_count' => $report->report_count,
                    'report_status' => $report->report_status,
                    'shop_name' => $report->review?->shop?->shop_name ?? 'ไม่ระบุ',
                    'shop_id' => $report->review?->shop_id,
                    'reviewer_name' => $report->review?->user?->username ?? 'ไม่ระบุ',
                    'rating' => $report->review?->rating ?? 5,
                    'comment' => $report->review?->comment ?? '',
                    'review_status' => $report->review?->status ?? 'show',
                    'review_images' => is_array($images) ? $images : (is_string($images) ? (json_decode($images, true) ?: [$images]) : []),
                    'review_date' => $report->review?->review_date,
                    'reporter_name' => $report->user?->username ?? 'ไม่ระบุ',
                    'reporter_phone' => $report->user?->phone,
                    'reporter_email' => $report->user?->email,
                ],
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Review reports retrieved successfully',
            'data' => $reports,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'review_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer'],
            'report_reason' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $report = ReviewReport::create([
            'review_id' => $request->review_id,
            'user_id' => $request->user_id,
            'report_reason' => $request->report_reason,
            'report_count' => 1,
            'report_date' => now(),
            'report_status' => 'active',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'รายงานความคิดเห็นเรียบร้อยแล้ว',
            'data' => $report,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $cleanId = (int) str_replace('rev_', '', $id);
        $report = ReviewReport::with(['user', 'review.user', 'review.shop'])->find($cleanId);

        if (! $report) {
            return response()->json([
                'status' => false,
                'message' => 'Review report not found',
            ], 404);
        }

        if ($request->filled('status')) {
            $statusInput = $request->input('status');
            $report->report_status = match ($statusInput) {
                'pending' => 'active',
                'resolved' => 'resolved',
                'progress' => 'progress',
                'dismissed' => 'dismissed',
                default => $statusInput,
            };
        }

        if ($request->has('admin_note')) {
            $report->admin_note = $request->input('admin_note');
        }

        $report->save();

        if ($request->has('review_status') && $report->review) {
            $report->review->status = $request->input('review_status');
            $report->review->save();
        }

        $rawStatus = $report->report_status ?: 'active';
        $mappedStatus = match ($rawStatus) {
            'active' => 'pending',
            'progress' => 'progress',
            'resolved', 'inactive', 'dismissed' => 'resolved',
            default => 'pending',
        };

        return response()->json([
            'status' => true,
            'message' => 'Review report updated successfully',
            'data' => [
                'id' => 'rev_' . $report->report_id,
                'problem_id' => 'rev_' . $report->report_id,
                'review_report_id' => $report->report_id,
                'is_review_report' => true,
                'status' => $mappedStatus,
                'report_status_raw' => $rawStatus,
                'admin_note' => $report->admin_note,
                'review_status' => $report->review?->status ?? 'show',
            ],
        ], 200);
    }

    public function toggleReview(Request $request, $id)
    {
        $cleanId = (int) str_replace('rev_', '', $id);
        $report = ReviewReport::with('review')->find($cleanId);

        if (! $report || ! $report->review) {
            return response()->json([
                'status' => false,
                'message' => 'Review not found for this report',
            ], 404);
        }

        $currentStatus = $report->review->status ?: 'show';
        $newStatus = ($currentStatus === 'show') ? 'hidden' : 'show';
        $report->review->status = $newStatus;
        $report->review->save();

        return response()->json([
            'status' => true,
            'message' => $newStatus === 'hidden' ? 'ซ่อนความคิดเห็นเรียบร้อยแล้ว' : 'เปิดแสดงความคิดเห็นตามปกติแล้ว',
            'data' => [
                'review_id' => $report->review->review_id,
                'review_status' => $newStatus,
            ],
        ], 200);
    }

    public function destroy($id)
    {
        $cleanId = (int) str_replace('rev_', '', $id);
        $report = ReviewReport::find($cleanId);

        if (! $report) {
            return response()->json([
                'status' => false,
                'message' => 'Review report not found',
            ], 404);
        }

        $report->delete();

        return response()->json([
            'status' => true,
            'message' => 'Review report deleted successfully',
        ], 200);
    }
}
