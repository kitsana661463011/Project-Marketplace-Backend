<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopReviewController extends Controller
{
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $review = ShopReview::create([
            'user_id' => $request->user_id,
            'shop_id' => $request->shop_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'review_date' => now(),
            'status' => 'show',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Review created successfully',
            'data' => $review->load('user'),
        ], 201);
    }
}
