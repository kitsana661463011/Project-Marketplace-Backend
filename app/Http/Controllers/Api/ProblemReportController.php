<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProblemReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProblemReportController extends Controller
{
    public function index(Request $request)
    {
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

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('report_date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('report_date', '<=', $request->input('end_date'));
        }

        $reports = $query->orderByDesc('report_date')->get()->map(function (ProblemReport $report) {
            return [
                'id' => $report->problem_id,
                'problem_id' => $report->problem_id,
                'description' => $report->description,
                'image' => $report->image,
                'report_date' => $report->report_date,
                'status' => $report->status,
                'report_type' => $report->report_type ?? 'other',
                'admin_note' => $report->admin_comment,
                'admin_comment' => $report->admin_comment,
                'user_id' => $report->user_id,
                'user_name' => $report->user?->username,
                'stall_id' => $report->stall_id,
                'stall_number' => $report->stall?->stall_number,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Problem reports retrieved successfully',
            'data' => $reports,
        ], 200);
    }

    public function update(Request $request, $id)
    {
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

        return response()->json([
            'status' => true,
            'message' => 'Problem report updated successfully',
            'data' => [
                'id' => $report->problem_id,
                'problem_id' => $report->problem_id,
                'description' => $report->description,
                'image' => $report->image,
                'report_date' => $report->report_date,
                'status' => $report->status,
                'report_type' => $report->report_type ?? 'other',
                'admin_note' => $report->admin_comment,
                'admin_comment' => $report->admin_comment,
                'user_name' => $report->user?->username,
                'stall_number' => $report->stall?->stall_number,
            ],
        ], 200);
    }
}
