<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SellerManagementController extends Controller
{
    private function formatApiDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format(DATE_ATOM);
            }

            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getImageUrl(?string $image): ?string
    {
        if (empty($image)) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, '/api/')) {
            return url($image);
        }

        $filename = basename($image);
        if (file_exists(storage_path('images/' . $filename))) {
            return url('/api/v1/images/' . $filename);
        }

        if (file_exists(storage_path('app/public/' . $filename))) {
            return asset('storage/' . $filename);
        }

        return url('/api/v1/images/' . $filename);
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->where('role', 'seller')
            ->where('document_status', 'approved')
            ->with(['stallBookings' => function ($query) {
                $query->where('status', 'approved')
                    ->with('stall:stall_id,stall_number');
            }]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('citizen_id', 'like', "%{$search}%");
            });
        }

        $sellers = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function (User $user) {
                $currentStalls = $user->stallBookings
                    ->pluck('stall.stall_number')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id' => $user->user_id,
                    'name' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'citizen_id' => $user->citizen_id,
                    'address' => $user->address,
                    'status' => $user->status,
                    'avatar' => $this->getImageUrl($user->profile_image),
                    'document_status' => $user->document_status,
                    'document_image' => $user->document_image,
                    'document_url' => $this->getImageUrl($user->document_image),
                    'reject_reason' => $user->reject_reason,
                    'current_stalls' => $currentStalls,
                    'created_at' => $this->formatApiDate($user->created_at),
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Current sellers retrieved successfully',
            'data' => $sellers,
        ], 200);
    }

    public function pending(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->where('document_status', 'pending')
            ->where(function ($q) {
                $q->where('role', 'buyer')
                    ->orWhere('role', 'seller');
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('citizen_id', 'like', "%{$search}%");
            });
        }

        $applications = $query->orderBy('submission_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->user_id,
                    'name' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'citizen_id' => $user->citizen_id,
                    'address' => $user->address ?? null,
                    'avatar' => $this->getImageUrl($user->profile_image),
                    'submission_date' => $this->formatApiDate($user->submission_date ?? $user->created_at),
                    'document_status' => $user->document_status,
                    'document_image' => $user->document_image,
                    'document_url' => $this->getImageUrl($user->document_image),
                    'reject_reason' => $user->reject_reason,
                    'status' => $user->status,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Pending seller applications retrieved successfully',
            'data' => $applications,
        ], 200);
    }

    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'citizen_id' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Seller not found',
                'data' => null,
            ], 404);
        }

        if ($request->has('citizen_id')) {
            $cleaned = preg_replace('/\D/', '', (string) $request->input('citizen_id'));
            if (!empty($cleaned)) {
                $user->citizen_id = $cleaned;
            }
        }

        if ($request->filled('address')) {
            $user->address = trim((string) $request->input('address'));
        }

        $user->document_status = 'approved';
        $user->role = 'seller';
        $user->status = 'active';
        $user->reject_reason = null;
        $user->save();

        try {
            Notification::create([
                'user_id' => $user->user_id,
                'message' => '🎉 ยินดีด้วย! คำขอสมัครเป็นผู้ค้าของคุณได้รับการอนุมัติเรียบร้อยแล้ว คุณสามารถเริ่มเปิดร้านค้าและจองแผงค้าได้ทันที',
                'notify_date' => now(),
                'type' => 'seller',
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create approve notification: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'Seller application approved successfully',
            'data' => [
                'id' => $user->user_id,
                'name' => $user->username,
                'citizen_id' => $user->citizen_id,
                'address' => $user->address,
                'document_status' => $user->document_status,
                'role' => $user->role,
                'status' => $user->status,
            ],
        ], 200);
    }

    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reject_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Seller not found',
                'data' => null,
            ], 404);
        }

        $reason = trim((string) $request->input('reject_reason', ''));
        if ($reason === '') {
            $reason = 'เอกสารหรือข้อมูลไม่ผ่านเกณฑ์การตรวจสอบ';
        }

        $user->document_status = 'rejected';
        $user->role = 'buyer';
        $user->status = 'active';
        $user->reject_reason = $reason;
        $user->save();

        try {
            Notification::create([
                'user_id' => $user->user_id,
                'message' => 'คำขอสมัครเป็นผู้ค้าไม่ผ่านการอนุมัติ: ' . $reason,
                'notify_date' => now(),
                'type' => 'seller',
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create reject notification: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'Seller application rejected successfully',
            'data' => [
                'id' => $user->user_id,
                'name' => $user->username,
                'document_status' => $user->document_status,
                'reject_reason' => $user->reject_reason,
                'status' => $user->status,
            ],
        ], 200);
    }
}
