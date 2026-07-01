<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
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

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->where('role', 'seller')
            ->where('document_status', 'approved')
            ->with(['stallBookings' => function ($query) {
                $query->where('status', 'approved')
                    ->with('stall:id,stall_number');
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
                    'document_status' => $user->document_status,
                    'document_image' => $user->document_image,
                    'document_url' => $user->document_image ? asset('api/images/' . $user->document_image) : null,
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
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->user_id,
                    'name' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'citizen_id' => $user->citizen_id,
                    'address' => $user->address ?? null,
                    'submission_date' => $this->formatApiDate($user->submission_date ?? $user->created_at),
                    'document_status' => $user->document_status,
                    'document_image' => $user->document_image,
                    'document_url' => $user->document_image ? asset('api/images/' . $user->document_image) : null,
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
            'citizen_id' => ['required', 'string', 'digits:13'],
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

        $validated = $validator->validated();
        $user->citizen_id = $validated['citizen_id'];
        $user->address = $request->filled('address') ? trim($request->input('address')) : $user->address;
        $user->document_status = 'approved';
        $user->role = 'seller';
        $user->status = 'active';
        $user->save();

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

    public function reject($id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Seller not found',
                'data' => null,
            ], 404);
        }

        $user->document_status = 'rejected';
        $user->status = 'inactive';
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Seller application rejected successfully',
            'data' => [
                'id' => $user->user_id,
                'name' => $user->username,
                'document_status' => $user->document_status,
                'status' => $user->status,
            ],
        ], 200);
    }
}
