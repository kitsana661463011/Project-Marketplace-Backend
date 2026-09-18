<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Stall;
use App\Models\StallBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    protected function getAllowedStatuses(): array
    {
        return ['pending', 'pending_review', 'approved', 'cancelled', 'refund_requested', 'refunded', 'renewal_pending'];
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
                'sb.rental_type',
                'sb.daily_price',
                'sb.monthly_price',
                'sb.entry_fee',
                'sb.security_deposit',
                'sb.total_amount',
                'sb.booking_date',
                'sb.start_date',
                'sb.end_date',
                'sb.renewal_end_date',
                'sb.renewal_amount',
                'sb.status',
                'sb.reject_reason',
                'u.username as user_name',
                'u.email as user_email',
                'u.phone as user_phone',
                's.stall_number',
                's.size as stall_size',
                's.status as stall_status',
                's.rental_type as stall_rental_type',
                's.daily_price as stall_daily_price',
                's.monthly_price as stall_monthly_price',
                's.entry_fee as stall_entry_fee',
                's.security_deposit as stall_security_deposit',
                's.has_electricity as stall_has_electricity',
                's.has_water as stall_has_water',
                'mz.zone_name',
                'p.payment_id',
                'p.amount',
                'p.payment_date',
                'p.payment_slip',
                'p.status as payment_status',
                'p.refund_reason',
                'p.refund_bank_name',
                'p.refund_account_number',
                'p.refund_account_name',
                'p.refund_slip',
                'p.refunded_at'
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
            'rental_type' => ['nullable', 'string', Rule::in(['daily', 'monthly'])],
            'daily_price' => ['nullable', 'numeric', 'min:0'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'entry_fee' => ['nullable', 'numeric', 'min:0'],
            'security_deposit' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $booking = StallBooking::create($request->only([
            'user_id', 'stall_id', 'booking_date', 'start_date', 'end_date', 'status',
            'rental_type', 'daily_price', 'monthly_price', 'entry_fee', 'security_deposit', 'total_amount',
        ]));

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
                $updateData = ['status' => 'approved'];
                if ($booking->status === 'renewal_pending' && $booking->renewal_end_date) {
                    $updateData['end_date'] = $booking->renewal_end_date;
                    if ($booking->renewal_amount) {
                        $updateData['total_amount'] = (float) $booking->total_amount + (float) $booking->renewal_amount;
                    }
                    $updateData['renewal_end_date'] = null;
                    $updateData['renewal_amount'] = null;
                }
                $booking->update($updateData);

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

        try {
            $stallNumber = $booking->stall ? $booking->stall->stall_number : 'แผงค้า';
            $isRenewal = ($booking->renewal_end_date != null);
            $msg = $isRenewal
                ? "🎉 คำขอต่อสัญญาแผงค้า {$stallNumber} ได้รับการอนุมัติแล้ว (ขยายสัญญาถึง: {$booking->end_date})"
                : "🎉 คำขอจองแผงค้า {$stallNumber} ได้รับการอนุมัติเรียบร้อยแล้ว สามารถเริ่มเข้าใช้งานแผงค้าได้";

            Notification::create([
                'user_id' => $booking->user_id,
                'message' => $msg,
                'notify_date' => now(),
                'type' => 'booking',
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create booking approve notification: ' . $e->getMessage());
        }

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
            'reject_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $rejectReason = $request->input('reject_reason') ?? $request->input('note');

        try {
            DB::transaction(function () use ($booking, $rejectReason) {
                $booking->update([
                    'status' => 'cancelled',
                    'reject_reason' => $rejectReason,
                ]);

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

        try {
            $stallNumber = $booking->stall ? $booking->stall->stall_number : 'แผงค้า';
            $reasonText = $rejectReason ? ": {$rejectReason}" : '';
            Notification::create([
                'user_id' => $booking->user_id,
                'message' => "❌ คำขอจองแผงค้า {$stallNumber} ถูกปฏิเสธ{$reasonText}",
                'notify_date' => now(),
                'type' => 'booking',
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create booking reject notification: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'Booking rejected successfully',
            'data' => $booking,
        ], 200);
    }

    public function requestRefund(Request $request, $booking_id)
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
            'refund_reason' => ['required', 'string', 'max:500'],
            'refund_bank_name' => ['required', 'string', 'max:100'],
            'refund_account_number' => ['required', 'string', 'max:50'],
            'refund_account_name' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        try {
            DB::transaction(function () use ($booking, $request) {
                $booking->update([
                    'status' => 'refund_requested',
                    'reject_reason' => $request->input('refund_reason'),
                ]);

                $payment = Payment::where('booking_id', $booking->booking_id)->first();
                if ($payment) {
                    $payment->update([
                        'status' => 'refund_requested',
                        'refund_reason' => $request->input('refund_reason'),
                        'refund_bank_name' => $request->input('refund_bank_name'),
                        'refund_account_number' => $request->input('refund_account_number'),
                        'refund_account_name' => $request->input('refund_account_name'),
                    ]);
                } else {
                    Payment::create([
                        'booking_id' => $booking->booking_id,
                        'amount' => 0,
                        'payment_date' => now(),
                        'status' => 'refund_requested',
                        'refund_reason' => $request->input('refund_reason'),
                        'refund_bank_name' => $request->input('refund_bank_name'),
                        'refund_account_number' => $request->input('refund_account_number'),
                        'refund_account_name' => $request->input('refund_account_name'),
                    ]);
                }

                $stall = Stall::find($booking->stall_id);
                if ($stall) {
                    $stall->update(['status' => 'available']);
                }
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Refund request failed',
                'data' => $e->getMessage(),
            ], 500);
        }

        $booking->refresh();
        $booking->load(['user', 'stall', 'payment']);

        return response()->json([
            'status' => true,
            'message' => 'Refund requested successfully',
            'data' => $booking,
        ], 200);
    }

    public function approveRefund(Request $request, $booking_id)
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
            'refund_slip' => ['nullable'],
            'refund_slip_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $refundSlipFilename = null;

        if ($request->hasFile('refund_slip_file')) {
            $file = $request->file('refund_slip_file');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'refund_slip_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists(storage_path('images'))) {
                @mkdir(storage_path('images'), 0777, true);
            }
            $file->storeAs('', $filename, 'custom_images');
            $refundSlipFilename = $filename;
        } elseif ($request->hasFile('refund_slip')) {
            $file = $request->file('refund_slip');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'refund_slip_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists(storage_path('images'))) {
                @mkdir(storage_path('images'), 0777, true);
            }
            $file->storeAs('', $filename, 'custom_images');
            $refundSlipFilename = $filename;
        } elseif ($request->filled('refund_slip') && is_string($request->input('refund_slip'))) {
            $refundSlipFilename = $request->input('refund_slip');
        }

        try {
            DB::transaction(function () use ($booking, $request, $refundSlipFilename) {
                $booking->update([
                    'status' => 'refunded',
                ]);

                $payment = Payment::where('booking_id', $booking->booking_id)->first();
                if ($payment) {
                    $note = $request->input('note') ?? $request->input('remark');
                    $updateData = [
                        'status' => 'refunded',
                        'refund_slip' => $refundSlipFilename ?? $payment->refund_slip,
                        'refunded_at' => now(),
                    ];

                    if ($note) {
                        if (Schema::hasColumn('payment', 'remark')) {
                            $updateData['remark'] = $note;
                        }
                        if (empty($payment->refund_reason)) {
                            $updateData['refund_reason'] = $note;
                        }
                    }

                    $payment->update($updateData);
                }

                $stall = Stall::find($booking->stall_id);
                if ($stall) {
                    $stall->update(['status' => 'available']);
                }
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Refunding failed',
                'data' => $e->getMessage(),
            ], 500);
        }

        $booking->refresh();
        $booking->load(['user', 'stall', 'payment']);

        try {
            $stallNumber = $booking->stall ? $booking->stall->stall_number : 'แผงค้า';
            Notification::create([
                'user_id' => $booking->user_id,
                'message' => "💵 ดำเนินการโอนเงินคืนสำหรับแผงค้า {$stallNumber} เรียบร้อยแล้ว (สามารถตรวจสอบหลักฐานสลิปการโอนได้ในประวัติการจอง)",
                'notify_date' => now(),
                'type' => 'refund',
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create refund notification: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'Refund processed successfully',
            'data' => $booking,
        ], 200);
    }

    public function renew(Request $request, $booking_id)
    {
        $booking = StallBooking::find($booking_id);

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'Booking not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'renewal_end_date' => ['required', 'date', 'after:end_date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'slip' => ['nullable'],
            'slip_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $slipFilename = null;
        if ($request->hasFile('slip_file')) {
            $file = $request->file('slip_file');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'renewal_slip_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists(storage_path('images'))) {
                @mkdir(storage_path('images'), 0777, true);
            }
            $file->storeAs('', $filename, 'custom_images');
            $slipFilename = $filename;
        } elseif ($request->hasFile('slip')) {
            $file = $request->file('slip');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'renewal_slip_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists(storage_path('images'))) {
                @mkdir(storage_path('images'), 0777, true);
            }
            $file->storeAs('', $filename, 'custom_images');
            $slipFilename = $filename;
        } elseif ($request->filled('slip') && is_string($request->input('slip'))) {
            $slipFilename = $request->input('slip');
        }

        try {
            DB::transaction(function () use ($booking, $request, $slipFilename) {
                $booking->update([
                    'status' => 'renewal_pending',
                    'renewal_end_date' => $request->input('renewal_end_date'),
                    'renewal_amount' => $request->input('amount'),
                ]);

                $payment = Payment::where('booking_id', $booking->booking_id)->first();
                $renewalRemark = 'ต่อสัญญาถึง ' . $request->input('renewal_end_date');
                if ($payment) {
                    $payment->update([
                        'amount' => $request->input('amount'),
                        'payment_date' => now()->format('Y-m-d'),
                        'payment_slip' => $slipFilename ?? $payment->payment_slip,
                        'status' => 'pending',
                        'remark' => $renewalRemark,
                    ]);
                } else {
                    Payment::create([
                        'booking_id' => $booking->booking_id,
                        'amount' => $request->input('amount'),
                        'payment_date' => now()->format('Y-m-d'),
                        'payment_slip' => $slipFilename,
                        'status' => 'pending',
                        'remark' => $renewalRemark,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Renewal request failed',
                'data' => $e->getMessage(),
            ], 500);
        }

        $booking->refresh();
        $booking->load(['user', 'stall', 'payment']);

        return response()->json([
            'status' => true,
            'message' => 'Renewal requested successfully',
            'data' => $booking,
        ], 200);
    }

    public function expiringSoon(Request $request)
    {
        $userId = $request->input('user_id');
        $query = DB::table('stall_booking as sb')
            ->leftJoin('user as u', 'u.user_id', '=', 'sb.user_id')
            ->leftJoin('stall as s', 's.stall_id', '=', 'sb.stall_id')
            ->leftJoin('market_zone as mz', 'mz.zone_id', '=', 's.zone_id')
            ->select(
                'sb.booking_id',
                'sb.user_id',
                'sb.stall_id',
                'sb.rental_type',
                'sb.daily_price',
                'sb.monthly_price',
                'sb.start_date',
                'sb.end_date',
                'sb.renewal_end_date',
                'sb.renewal_amount',
                'sb.status',
                's.stall_number',
                'mz.zone_name'
            )
            ->where('sb.status', 'approved');

        if ($userId) {
            $query->where('sb.user_id', $userId);
        }

        $today = now()->startOfDay();
        $fiveDaysLater = now()->addDays(5)->endOfDay();

        $query->whereBetween('sb.end_date', [$today->format('Y-m-d'), $fiveDaysLater->format('Y-m-d')]);

        $expiringBookings = $query->orderBy('sb.end_date', 'asc')->get()->map(function ($b) use ($today) {
            $endDate = \Carbon\Carbon::parse($b->end_date)->startOfDay();
            $daysLeft = $today->diffInDays($endDate, false);
            $b->days_left = max(0, (int)$daysLeft);
            $b->is_expiring_soon = true;
            return $b;
        });

        return response()->json([
            'status' => true,
            'message' => 'Expiring bookings retrieved successfully',
            'data' => $expiringBookings,
        ], 200);
    }
}
