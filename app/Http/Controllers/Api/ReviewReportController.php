<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReviewReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewReportController extends Controller
{
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
}
