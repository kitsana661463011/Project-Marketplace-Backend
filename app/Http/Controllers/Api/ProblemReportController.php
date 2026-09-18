<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ProblemReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProblemReportController extends Controller
{
    private function normalizeReportType(?string $category): string
    {
        if (! $category) {
            return 'other';
        }
        $cat = mb_strtolower(trim($category));
        if (str_contains($cat, 'ไฟฟ้า') || $cat === 'electric') {
            return 'electric';
        }
        if (str_contains($cat, 'ประปา') || $cat === 'water') {
            return 'water';
        }
        if (str_contains($cat, 'โครงสร้าง') || $cat === 'structure') {
            return 'structure';
        }
        if (str_contains($cat, 'ความสะอาด') || $cat === 'clean') {
            return 'clean';
        }
        if (str_contains($cat, 'ความคิดเห็น') || $cat === 'feedback') {
            return 'feedback';
        }

        return 'other';
    }

    public function index(Request $request)
    {
        $reqType = $request->input('type', $request->input('category', 'all'));
        $normType = $this->normalizeReportType($reqType);

        $reports = collect();

        // 1. Fetch from problem_report table unless explicitly filtering feedback only where we prioritize review_report
        if ($reqType === 'all' || $normType !== 'feedback') {
            $query = ProblemReport::query()->with(['user', 'stall']);

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhereHas('stall', function ($stallQuery) use ($search) {
                            $stallQuery->where('stall_number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('username', 'like', "%{$search}%");
                        });
                });
            }

            if ($reqType !== 'all') {
                $thaiCategoryMap = [
                    'electric' => 'ไฟฟ้า',
                    'water' => 'ประปา',
                    'structure' => 'โครงสร้าง',
                    'clean' => 'ความสะอาด',
                    'feedback' => 'ความคิดเห็น',
                ];
                $thaiKeyword = $thaiCategoryMap[$normType] ?? null;

                if ($thaiKeyword) {
                    $query->where('description', 'like', "%{$thaiKeyword}%");
                }
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->filled('start_date')) {
                $query->whereDate('report_date', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('report_date', '<=', $request->input('end_date'));
            }

            $problemReports = $query->orderByDesc('report_date')->get()->map(function (ProblemReport $report) {
                $rawType = $this->normalizeReportType($report->description);

                return [
                    'id' => (string) $report->problem_id,
                    'problem_id' => $report->problem_id,
                    'description' => $report->description,
                    'image' => $report->image,
                    'report_date' => $report->report_date,
                    'status' => $report->status,
                    'report_type' => $rawType,
                    'admin_note' => $report->admin_comment,
                    'admin_comment' => $report->admin_comment,
                    'user_id' => $report->user_id,
                    'user_name' => $report->user?->username,
                    'stall_id' => $report->stall_id,
                    'stall_number' => $report->stall?->stall_number,
                    'is_review_report' => false,
                ];
            });

            $reports = $reports->concat($problemReports);
        }

        // 2. Fetch from review_report table if filter is 'all' or 'feedback'
        if ($reqType === 'all' || $normType === 'feedback') {
            $isAdminRequest = $request->is('api/admin/*') || $request->header('X-Admin-Request') === 'true';
            // Only include review reports if it is an admin request or if user_id is specified (personal reports)
            if ($isAdminRequest || $request->filled('user_id')) {
                $reviewCtrl = new ReviewReportController();
                $reviewReportsResponse = $reviewCtrl->index($request);
                $reviewReportsData = $reviewReportsResponse->getData(true);
                if (!empty($reviewReportsData['data']) && is_array($reviewReportsData['data'])) {
                    $reports = $reports->concat($reviewReportsData['data']);
                }
            }
        }

        // Sort all merged reports descending by report_date
        $sortedReports = $reports->sortByDesc(function ($item) {
            return $item['report_date'] ?? '';
        })->values();

        return response()->json([
            'status' => true,
            'message' => 'Problem reports retrieved successfully',
            'data' => $sortedReports,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        if (str_starts_with((string) $id, 'rev_')) {
            $reviewCtrl = new ReviewReportController();
            return $reviewCtrl->update($request, $id);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'in:pending,progress,resolved'],
            'admin_note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $report = ProblemReport::find($id);

        if (! $report) {
            // Check if it's a review_report id without 'rev_' prefix
            $reviewReport = \App\Models\ReviewReport::find($id);
            if ($reviewReport) {
                $reviewCtrl = new ReviewReportController();
                return $reviewCtrl->update($request, (string) $id);
            }

            return response()->json([
                'status' => false,
                'message' => 'Problem report not found',
                'data' => null,
            ], 404);
        }

        $payload = [
            'status' => $request->input('status', $report->status),
        ];

        if ($request->has('admin_note')) {
            $payload['admin_comment'] = $request->input('admin_note');
        }

        $report->fill($payload);
        $report->save();

        try {
            $statusThai = match ($report->status) {
                'progress' => 'กำลังดำเนินการ',
                'resolved' => 'แก้ไข/เสร็จสิ้นแล้ว',
                default => 'ได้รับการอัปเดต',
            };
            $notePart = !empty($report->admin_comment) ? " (ข้อความตอบกลับ: {$report->admin_comment})" : '';
            $descBrief = Str::limit($report->description, 35);
            Notification::create([
                'user_id' => $report->user_id,
                'message' => "📢 ปัญหาที่คุณแจ้ง \"{$descBrief}\" {$statusThai}{$notePart}",
                'notify_date' => now(),
                'type' => 'problem',
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create problem report notification: ' . $e->getMessage());
        }

        $rawType = $this->normalizeReportType($report->description);

        return response()->json([
            'status' => true,
            'message' => 'Problem report updated successfully',
            'data' => [
                'id' => (string) $report->problem_id,
                'problem_id' => $report->problem_id,
                'description' => $report->description,
                'image' => $report->image,
                'report_date' => $report->report_date,
                'status' => $report->status,
                'report_type' => $rawType,
                'admin_note' => $report->admin_comment,
                'admin_comment' => $report->admin_comment,
                'user_name' => $report->user?->username,
                'stall_number' => $report->stall?->stall_number,
                'is_review_report' => false,
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
            'location' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'category' => ['required', 'string'],
            'image' => ['nullable'],
            'stall_id' => ['nullable', 'integer', 'exists:stall,stall_id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find stall by stall number / location or use stall_id
        if ($request->filled('stall_id')) {
            $stallId = $request->input('stall_id');
        } else {
            $stallNumber = $request->filled('location') ? trim($request->input('location')) : '';
            $stall = \App\Models\Stall::where('stall_number', 'like', "%{$stallNumber}%")->first();
            $stallId = $stall ? $stall->stall_id : 1; // Fallback to stall_id 1 if not found
        }

        // Handle image upload
        $imageName = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $imageName = 'problem_report_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists(storage_path('images'))) {
                @mkdir(storage_path('images'), 0777, true);
            }
            $file->storeAs('', $imageName, 'custom_images');
        } elseif ($request->filled('image')) {
            $imageName = $request->input('image');
        }

        $category = $request->input('category');
        $reportType = $this->normalizeReportType($category);
        $fullDescription = "[หมวดหมู่: {$category}] " . $request->input('description');

        $report = ProblemReport::create([
            'user_id' => $request->input('user_id'),
            'stall_id' => $stallId,
            'description' => $fullDescription,
            'image' => $imageName,
            'report_date' => now(),
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Problem report submitted successfully',
            'data' => [
                'id' => $report->problem_id,
                'problem_id' => $report->problem_id,
                'description' => $report->description,
                'image' => $report->image,
                'report_date' => $report->report_date,
                'status' => $report->status,
                'report_type' => $reportType,
                'stall_id' => $report->stall_id,
                'user_id' => $report->user_id,
            ],
        ], 201);
    }
}
