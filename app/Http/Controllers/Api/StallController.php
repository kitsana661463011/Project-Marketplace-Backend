<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StallController extends Controller
{
    public function index()
    {
        $stalls = Stall::with(['zone'])->get();

        return response()->json([
            'status' => true,
            'message' => 'Stalls retrieved successfully',
            'data' => $stalls,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'stall_number' => ['required', 'string', 'max:20'],
            'size' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['available', 'occupied', 'maintenance'])],
            'zone_id' => ['required', 'integer', 'exists:market_zone,zone_id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $stall = Stall::create($request->only(['stall_number', 'size', 'status', 'zone_id', 'start_date', 'end_date']));

        return response()->json([
            'status' => true,
            'message' => 'Stall created successfully',
            'data' => $stall,
        ], 201);
    }

    public function show($id)
    {
        $stall = Stall::with(['zone'])->find($id);

        if (! $stall) {
            return response()->json([
                'status' => false,
                'message' => 'Stall not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Stall retrieved successfully',
            'data' => $stall,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $stall = Stall::find($id);

        if (! $stall) {
            return response()->json([
                'status' => false,
                'message' => 'Stall not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'stall_number' => ['sometimes', 'string', 'max:20'],
            'size' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(['available', 'occupied', 'maintenance'])],
            'zone_id' => ['sometimes', 'integer', 'exists:market_zone,zone_id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $stall->update($request->only(['stall_number', 'size', 'status', 'zone_id', 'start_date', 'end_date']));

        return response()->json([
            'status' => true,
            'message' => 'Stall updated successfully',
            'data' => $stall->fresh(),
        ], 200);
    }

    public function destroy($id)
    {
        $stall = Stall::find($id);

        if (! $stall) {
            return response()->json([
                'status' => false,
                'message' => 'Stall not found',
                'data' => null,
            ], 404);
        }

        $stall->delete();

        return response()->json([
            'status' => true,
            'message' => 'Stall deleted successfully',
            'data' => null,
        ], 200);
    }
}
