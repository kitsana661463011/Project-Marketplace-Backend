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

    private function uploadStallImage($file, $oldImage = null, $slot = 'img1')
    {
        if (!file_exists(storage_path('images'))) {
            @mkdir(storage_path('images'), 0777, true);
        }

        if ($oldImage && file_exists(storage_path('images/' . $oldImage))) {
            @unlink(storage_path('images/' . $oldImage));
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = 'stall_' . $slot . '_' . time() . '_' . uniqid() . '.' . $ext;
        $file->move(storage_path('images'), $filename);

        return $filename;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'stall_number' => ['required', 'string', 'max:20'],
            'size' => ['nullable', 'string', 'max:50'],
            'price' => ['nullable', 'numeric'],
            'rental_type' => ['nullable', Rule::in(['daily', 'monthly'])],
            'daily_price' => ['nullable', 'numeric'],
            'monthly_price' => ['nullable', 'numeric'],
            'entry_fee' => ['nullable', 'numeric'],
            'security_deposit' => ['nullable', 'numeric'],
            'has_electricity' => ['nullable', 'boolean'],
            'has_water' => ['nullable', 'boolean'],
            'image1' => ['nullable'],
            'image2' => ['nullable'],
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

        $data = $request->only([
            'stall_number', 'size', 'price', 'rental_type', 'daily_price',
            'monthly_price', 'entry_fee', 'security_deposit', 'has_electricity',
            'has_water', 'status', 'zone_id', 'start_date', 'end_date'
        ]);

        if (!isset($data['has_electricity'])) {
            $data['has_electricity'] = true;
        }
        if (!isset($data['has_water'])) {
            $data['has_water'] = true;
        }

        if (isset($data['daily_price']) && !isset($data['price'])) {
            $data['price'] = $data['daily_price'];
        }

        $existingStall = Stall::where('stall_number', $data['stall_number'])->first();
        if ($existingStall) {
            if ($request->hasFile('image1')) {
                $data['image1'] = $this->uploadStallImage($request->file('image1'), $existingStall->image1, 'img1');
            }
            if ($request->hasFile('image2')) {
                $data['image2'] = $this->uploadStallImage($request->file('image2'), $existingStall->image2, 'img2');
            }
            $existingStall->update($data);
            $stall = $existingStall;
        } else {
            if ($request->hasFile('image1')) {
                $data['image1'] = $this->uploadStallImage($request->file('image1'), null, 'img1');
            }
            if ($request->hasFile('image2')) {
                $data['image2'] = $this->uploadStallImage($request->file('image2'), null, 'img2');
            }
            $stall = Stall::create($data);
        }

        return response()->json([
            'status' => true,
            'message' => 'Stall saved successfully',
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
            'price' => ['nullable', 'numeric'],
            'rental_type' => ['nullable', Rule::in(['daily', 'monthly'])],
            'daily_price' => ['nullable', 'numeric'],
            'monthly_price' => ['nullable', 'numeric'],
            'entry_fee' => ['nullable', 'numeric'],
            'security_deposit' => ['nullable', 'numeric'],
            'has_electricity' => ['nullable', 'boolean'],
            'has_water' => ['nullable', 'boolean'],
            'image1' => ['nullable'],
            'image2' => ['nullable'],
            'remove_image1' => ['nullable', 'boolean'],
            'remove_image2' => ['nullable', 'boolean'],
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

        $data = $request->only([
            'stall_number', 'size', 'price', 'rental_type', 'daily_price',
            'monthly_price', 'entry_fee', 'security_deposit', 'has_electricity',
            'has_water', 'status', 'zone_id', 'start_date', 'end_date'
        ]);

        if (isset($data['daily_price']) && !isset($data['price'])) {
            $data['price'] = $data['daily_price'];
        }

        if ($request->boolean('remove_image1')) {
            if ($stall->image1 && file_exists(storage_path('images/' . $stall->image1))) {
                @unlink(storage_path('images/' . $stall->image1));
            }
            $data['image1'] = null;
        } elseif ($request->hasFile('image1')) {
            $data['image1'] = $this->uploadStallImage($request->file('image1'), $stall->image1, 'img1');
        }

        if ($request->boolean('remove_image2')) {
            if ($stall->image2 && file_exists(storage_path('images/' . $stall->image2))) {
                @unlink(storage_path('images/' . $stall->image2));
            }
            $data['image2'] = null;
        } elseif ($request->hasFile('image2')) {
            $data['image2'] = $this->uploadStallImage($request->file('image2'), $stall->image2, 'img2');
        }

        $stall->update($data);

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

        if ($stall->image1 && file_exists(storage_path('images/' . $stall->image1))) {
            @unlink(storage_path('images/' . $stall->image1));
        }
        if ($stall->image2 && file_exists(storage_path('images/' . $stall->image2))) {
            @unlink(storage_path('images/' . $stall->image2));
        }

        $stall->delete();

        return response()->json([
            'status' => true,
            'message' => 'Stall deleted successfully',
            'data' => null,
        ], 200);
    }
}
