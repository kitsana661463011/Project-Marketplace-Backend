<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopReviewController extends Controller
{
    private function uploadImage($file, $oldImage = null)
    {
        if ($oldImage && file_exists(storage_path('images/' . $oldImage))) {
            @unlink(storage_path('images/' . $oldImage));
        }

        $ext = $file->getClientOriginalExtension() ?: 'png';
        $filename = time() . '_review_' . uniqid() . '.' . $ext;
        $file->move(storage_path('images'), $filename);

        return $filename;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $reviews = ShopReview::with('user')
            ->where('shop_id', $request->shop_id)
            ->where('status', 'show')
            ->orderBy('review_id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Reviews retrieved successfully',
            'data' => $reviews,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
            'shop_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
            'images' => ['nullable'],
            'images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $imagesList = [];
        $imageFiles = $request->hasFile('images')
            ? $request->file('images')
            : ($request->hasFile('images[]') ? $request->file('images[]') : null);

        if ($imageFiles) {
            foreach ((array) $imageFiles as $file) {
                $imagesList[] = $this->uploadImage($file);
            }
        } else if ($request->filled('images')) {
            $inputImages = $request->input('images');
            if (is_string($inputImages)) {
                $decoded = json_decode($inputImages, true);
                $imagesList = is_array($decoded)
                    ? $decoded
                    : array_filter(array_map('trim', explode(',', $inputImages)));
            } else if (is_array($inputImages)) {
                $imagesList = $inputImages;
            }
        }

        $data = [
            'user_id' => $request->user_id,
            'shop_id' => $request->shop_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'review_date' => now(),
            'status' => 'show',
        ];

        if (!empty($imagesList)) {
            $data['review_images'] = $imagesList;
        }

        $review = ShopReview::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Review created successfully',
            'data' => $review->load('user'),
        ], 201);
    }
}
