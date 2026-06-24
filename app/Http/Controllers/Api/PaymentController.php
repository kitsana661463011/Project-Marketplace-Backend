<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with(['booking'])->get();

        return response()->json([
            'status' => true,
            'message' => 'Payments retrieved successfully',
            'data' => $payments,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => ['required', 'integer', 'exists:stall_booking,booking_id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'payment_slip' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $payment = Payment::create($request->only(['booking_id', 'amount', 'payment_date', 'payment_slip', 'status']));

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
            'status' => ['sometimes', Rule::in(['pending', 'verified', 'rejected'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $payment->update($request->only(['booking_id', 'amount', 'payment_date', 'payment_slip', 'status']));

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

        $payment->delete();

        return response()->json([
            'status' => true,
            'message' => 'Payment deleted successfully',
            'data' => null,
        ], 200);
    }
}
