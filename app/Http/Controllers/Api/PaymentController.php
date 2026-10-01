<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with(['booking.user', 'booking.stall'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('payment_id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Payments retrieved successfully',
            'data' => $payments,
        ], 200);
    }

    private function uploadImage($file, $oldImage = null)
    {
        if (!file_exists(storage_path('images'))) {
            @mkdir(storage_path('images'), 0777, true);
        }

        if ($oldImage && file_exists(storage_path('images/' . $oldImage))) {
            @unlink(storage_path('images/' . $oldImage));
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = 'payment_slip_' . time() . '_' . uniqid() . '.' . $ext;
        $file->move(storage_path('images'), $filename);

        return $filename;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => ['required', 'integer', 'exists:stall_booking,booking_id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'payment_slip' => ['nullable'],
            'payment_slip_file' => ['nullable'],
            'destination_bank' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['pending', 'verified', 'rejected', 'refund_requested', 'refunded'])],
            'refund_reason' => ['nullable', 'string'],
            'refund_bank_name' => ['nullable', 'string', 'max:100'],
            'refund_account_number' => ['nullable', 'string', 'max:50'],
            'refund_account_name' => ['nullable', 'string', 'max:100'],
            'refund_slip' => ['nullable', 'string', 'max:255'],
            'refunded_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'booking_id', 'amount', 'payment_date', 'payment_slip', 'destination_bank', 'status',
            'refund_reason', 'refund_bank_name', 'refund_account_number', 'refund_account_name', 'refund_slip', 'refunded_at'
        ]);

        if ($request->hasFile('payment_slip_file')) {
            $data['payment_slip'] = $this->uploadImage($request->file('payment_slip_file'));
        } elseif ($request->hasFile('payment_slip')) {
            $data['payment_slip'] = $this->uploadImage($request->file('payment_slip'));
        }

        $payment = Payment::create($data);

        // แจ้งเตือนผู้ใช้เมื่อส่งสลิปชำระเงินเรียบร้อย
        try {
            $booking = $payment->booking()->with('stall')->first();
            if ($booking && $booking->user_id) {
                $stallNumber = $booking->stall ? $booking->stall->stall_number : 'แผงค้า';
                Notification::create([
                    'user_id' => $booking->user_id,
                    'title' => 'ได้รับหลักฐานการชำระเงินแล้ว',
                    'message' => "📄 ได้รับหลักฐานการชำระเงินสำหรับแผงค้า {$stallNumber} เรียบร้อยแล้ว ระบบกำลังรอเจ้าหน้าที่ตรวจสอบ",
                    'notify_date' => now(),
                    'type' => 'booking',
                    'reference_id' => $booking->booking_id,
                    'is_read' => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to create payment slip notification: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment created successfully',
            'data' => $payment,
        ], 201);
    }

    public function show($id)
    {
        $payment = Payment::with(['booking'])->find($id);

        if (! $payment) {
            return response()->json([
                'status' => false,
                'message' => 'Payment not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment retrieved successfully',
            'data' => $payment,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (! $payment) {
            return response()->json([
                'status' => false,
                'message' => 'Payment not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'booking_id' => ['sometimes', 'integer', 'exists:stall_booking,booking_id'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'payment_slip' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['pending', 'verified', 'rejected', 'refund_requested', 'refunded'])],
            'refund_reason' => ['nullable', 'string'],
            'refund_bank_name' => ['nullable', 'string', 'max:100'],
            'refund_account_number' => ['nullable', 'string', 'max:50'],
            'refund_account_name' => ['nullable', 'string', 'max:100'],
            'refund_slip' => ['nullable', 'string', 'max:255'],
            'refunded_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $payment->update($request->only([
            'booking_id', 'amount', 'payment_date', 'payment_slip', 'status',
            'refund_reason', 'refund_bank_name', 'refund_account_number', 'refund_account_name', 'refund_slip', 'refunded_at'
        ]));

        if ($request->has('payment_slip') || $request->hasFile('payment_slip') || $request->hasFile('payment_slip_file')) {
            try {
                $booking = $payment->booking()->with('stall')->first();
                if ($booking && $booking->user_id) {
                    $stallNumber = $booking->stall ? $booking->stall->stall_number : 'แผงค้า';
                    Notification::create([
                        'user_id' => $booking->user_id,
                        'title' => 'อัปโหลดหลักฐานการชำระเงินใหม่แล้ว',
                        'message' => "📄 ได้รับหลักฐานการชำระเงินใหม่สำหรับแผงค้า {$stallNumber} เรียบร้อยแล้ว กำลังรอเจ้าหน้าที่ตรวจสอบ",
                        'notify_date' => now(),
                        'type' => 'booking',
                        'reference_id' => $booking->booking_id,
                        'is_read' => false,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to create payment slip update notification: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment updated successfully',
            'data' => $payment->fresh(),
        ], 200);
    }

    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (! $payment) {
            return response()->json([
                'status' => false,
                'message' => 'Payment not found',
                'data' => null,
            ], 404);
        }

        if ($payment->payment_slip && file_exists(storage_path('images/' . $payment->payment_slip))) {
            @unlink(storage_path('images/' . $payment->payment_slip));
        }
        if ($payment->refund_slip && file_exists(storage_path('images/' . $payment->refund_slip))) {
            @unlink(storage_path('images/' . $payment->refund_slip));
        }

        $payment->delete();

        return response()->json([
            'status' => true,
            'message' => 'Payment deleted successfully',
            'data' => null,
        ], 200);
    }
}
