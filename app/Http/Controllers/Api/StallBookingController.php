<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StallBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StallBookingController extends Controller
{
    public function index()
    {
        $bookings = StallBooking::with(['user', 'stall'])->get();

        return response()->json([
            'status' => true,
            'message' => 'Bookings retrieved successfully',
            'data' => $bookings,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
            'stall_id' => ['required', 'integer', 'exists:stall,stall_id'],
            'booking_date' => ['nullable', 'date'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['pending', 'approved', 'cancelled'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $booking = StallBooking::create($request->only(['user_id', 'stall_id', 'booking_date', 'start_date', 'end_date', 'status']));

        return response()->json([
            'status' => true,
            'message' => 'Booking created successfully',
            'data' => $booking,
        ], 201);
    }

    public function show($id)
    {
        $booking = StallBooking::with(['user', 'stall'])->find($id);

        if (! $booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Booking retrieved successfully',
            'data' => $booking,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $booking = StallBooking::find($id);

        if (! $booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => ['sometimes', 'integer', 'exists:user,user_id'],
            'stall_id' => ['sometimes', 'integer', 'exists:stall,stall_id'],
            'booking_date' => ['nullable', 'date'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'cancelled'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $booking->update($request->only(['user_id', 'stall_id', 'booking_date', 'start_date', 'end_date', 'status']));

        return response()->json([
            'status' => true,
            'message' => 'Booking updated successfully',
            'data' => $booking->fresh(),
        ], 200);
    }

    public function destroy($id)
    {
        $booking = StallBooking::find($id);

        if (! $booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found',
                'data' => null,
            ], 404);
        }

        $booking->delete();

        return response()->json([
            'status' => true,
            'message' => 'Booking deleted successfully',
            'data' => null,
        ], 200);
    }
}
