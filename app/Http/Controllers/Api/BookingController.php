<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Stall;
use App\Models\StallBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    protected function getAllowedStatuses(): array
    {
        return ['pending', 'pending_review', 'approved', 'cancelled'];
    }

    public function index(Request $request)
    {
        $query = DB::table('stall_booking as sb')
            ->leftJoin('user as u', 'u.user_id', '=', 'sb.user_id')
            ->leftJoin('stall as s', 's.stall_id', '=', 'sb.stall_id')
            ->leftJoin('market_zone as mz', 'mz.zone_id', '=', 's.zone_id')
            ->leftJoin('payment as p', 'p.booking_id', '=', 'sb.booking_id')
            ->select(
                'sb.booking_id',
                'sb.user_id',
                'sb.stall_id',
                'sb.booking_date',
                'sb.start_date',
                'sb.end_date',
                'sb.status',
                'u.username as user_name',
                'u.email as user_email',
                'u.phone as user_phone',
                's.stall_number',
                's.size as stall_size',
                's.status as stall_status',
                'mz.zone_name',
                'p.payment_id',
                'p.amount',
                'p.payment_date',
                'p.payment_slip',
                'p.status as payment_status'
            );

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sb.booking_id', 'like', "%{$search}%")
                    ->orWhere('u.username', 'like', "%{$search}%")
                    ->orWhere('u.email', 'like', "%{$search}%")
                    ->orWhere('s.stall_number', 'like', "%{$search}%")
                    ->orWhere('sb.status', 'like', "%{$search}%");
            });
        }

        $startDate = $request->input('start_date', $request->input('from_date'));
        $endDate = $request->input('end_date', $request->input('to_date'));

        if ($startDate) {
            $query->whereDate('sb.booking_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('sb.booking_date', '<=', $endDate);
        }

        $userId = $request->input('user_id');
        if ($userId) {
            $query->where('sb.user_id', $userId);
        }

        $status = $request->input('status');
        if ($status) {
            $query->where('sb.status', $status);
        }

        $bookings = $query->orderBy('sb.booking_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Bookings retrieved successfully',
            'data' => $bookings,
        ], 200);
    }

    public function show($booking_id)
    {
        $booking = StallBooking::with(['user', 'stall', 'payment'])->find($booking_id);

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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
            'stall_id' => ['required', 'integer', 'exists:stall,stall_id'],
            'booking_date' => ['nullable', 'date'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in($this->getAllowedStatuses())],
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

    public function update(Request $request, $booking_id)
    {
        $booking = StallBooking::find($booking_id);

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
            'status' => ['sometimes', Rule::in($this->getAllowedStatuses())],
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

    public function destroy($booking_id)
    {
        $booking = StallBooking::find($booking_id);

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

    public function approve(Request $request, $booking_id)
    {
        $booking = StallBooking::find($booking_id);

        if (! $booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        try {
            DB::transaction(function () use ($booking) {
                $booking->update(['status' => 'approved']);

                $payment = Payment::where('booking_id', $booking->booking_id)->first();
                if ($payment) {
                    $payment->update(['status' => 'verified']);
                } else {
                    Payment::create([
                        'booking_id' => $booking->booking_id,
                        'amount' => 0,
                        'payment_date' => now()->format('Y-m-d'),
                        'payment_slip' => null,
                        'status' => 'verified',
                    ]);
                }

                $stall = Stall::find($booking->stall_id);
                if ($stall) {
                    $stall->update(['status' => 'occupied']);
                }
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Approval failed',
                'data' => $e->getMessage(),
            ], 500);
        }

        $booking->refresh();
        $booking->load(['user', 'stall', 'payment']);

        return response()->json([
            'status' => true,
            'message' => 'Booking approved successfully',
            'data' => $booking,
        ], 200);
    }

    public function pending(Request $request, $booking_id)
    {
        $booking = StallBooking::find($booking_id);

        if (! $booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        try {
            DB::transaction(function () use ($booking) {
                $booking->update(['status' => 'pending']);

                $payment = Payment::where('booking_id', $booking->booking_id)->first();
                if ($payment) {
                    $payment->update(['status' => 'pending']);
                }

                $stall = Stall::find($booking->stall_id);
                if ($stall) {
                    $stall->update(['status' => 'available']);
                }
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Pending action failed',
                'data' => $e->getMessage(),
            ], 500);
        }

        $booking->refresh();
        $booking->load(['user', 'stall', 'payment']);

        return response()->json([
            'status' => true,
            'message' => 'Booking moved back to pending successfully',
            'data' => $booking,
        ], 200);
    }

    public function hold(Request $request, $booking_id)
    {
        return $this->pending($request, $booking_id);
    }

    public function reject(Request $request, $booking_id)
    {
        $booking = StallBooking::find($booking_id);

        if (! $booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        try {
            DB::transaction(function () use ($booking) {
                $booking->update(['status' => 'cancelled']);

                $payment = Payment::where('booking_id', $booking->booking_id)->first();
                if ($payment) {
                    $payment->update(['status' => 'rejected']);
                }

                $stall = Stall::find($booking->stall_id);
                if ($stall) {
                    $stall->update(['status' => 'available']);
                }
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Rejection failed',
                'data' => $e->getMessage(),
            ], 500);
        }

        $booking->refresh();
        $booking->load(['user', 'stall', 'payment']);

        return response()->json([
            'status' => true,
            'message' => 'Booking rejected successfully',
            'data' => $booking,
        ], 200);
    }
}
