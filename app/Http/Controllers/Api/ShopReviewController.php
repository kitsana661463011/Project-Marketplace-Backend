<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopReview;
use App\Models\ReviewReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopReviewController extends Controller
{
    private function uploadImage($file, $oldImage = null)
    {
        if (!file_exists(storage_path('images'))) {
            @mkdir(storage_path('images'), 0777, true);
        }

        if ($oldImage && file_exists(storage_path('images/' . $oldImage))) {
            @unlink(storage_path('images/' . $oldImage));
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = 'shop_review_' . time() . '_' . uniqid() . '.' . $ext;
        $file->move(storage_path('images'), $filename);

        return $filename;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $userId = $request->input('user_id');

        $reviews = ShopReview::with('user')
            ->where('shop_id', $request->shop_id)
            ->where('status', 'show')
            ->orderBy('review_id', 'desc')
            ->get()
            ->map(function ($review) use ($userId) {
                $reviewArray = $review->toArray();

                $likesCount = ReviewReaction::where('review_id', $review->review_id)
                    ->where('reaction_type', 'like')
                    ->count();
                $dislikesCount = ReviewReaction::where('review_id', $review->review_id)
                    ->where('reaction_type', 'dislike')
                    ->count();

                $userReaction = null;
                if ($userId) {
                    $reaction = ReviewReaction::where('review_id', $review->review_id)
                        ->where('user_id', $userId)
                        ->first();
                    if ($reaction) {
                        $userReaction = $reaction->reaction_type;
                    }
                }

                $reviewArray['likes'] = $likesCount;
                $reviewArray['dislikes'] = $dislikesCount;
                $reviewArray['user_reaction'] = $userReaction;
                $reviewArray['is_liked'] = $userReaction === 'like';
                $reviewArray['is_disliked'] = $userReaction === 'dislike';

                return $reviewArray;
            });

        return response()->json([
            'status' => true,
            'message' => 'Reviews retrieved successfully',
            'data' => $reviews,
        ], 200);
    }

    public function react(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'review_id' => ['required', 'integer', 'exists:shop_review,review_id'],
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
            'reaction_type' => ['required', 'string', 'in:like,dislike'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $reviewId = (int)$request->input('review_id');
        $userId = (int)$request->input('user_id');
        $reactionType = $request->input('reaction_type');

        $existing = ReviewReaction::where('review_id', $reviewId)
            ->where('user_id', $userId)
            ->first();

        $action = '';
        if ($existing) {
            if ($existing->reaction_type === $reactionType) {
                // Tapping the same reaction toggles it off
                $existing->delete();
                $action = 'removed';
            } else {
                // Switching reaction from like to dislike or vice versa
                $existing->reaction_type = $reactionType;
                $existing->save();
                $action = 'updated';
            }
        } else {
            ReviewReaction::create([
                'review_id' => $reviewId,
                'user_id' => $userId,
                'reaction_type' => $reactionType,
            ]);
            $action = 'created';
        }

        $likesCount = ReviewReaction::where('review_id', $reviewId)
            ->where('reaction_type', 'like')
            ->count();
        $dislikesCount = ReviewReaction::where('review_id', $reviewId)
            ->where('reaction_type', 'dislike')
            ->count();

        $currentReaction = ReviewReaction::where('review_id', $reviewId)
            ->where('user_id', $userId)
            ->first();

        $currentUserReaction = $currentReaction ? $currentReaction->reaction_type : null;

        return response()->json([
            'status' => true,
            'message' => 'Reaction updated successfully',
            'data' => [
                'action' => $action,
                'review_id' => $reviewId,
                'likes' => $likesCount,
                'dislikes' => $dislikesCount,
                'user_reaction' => $currentUserReaction,
                'is_liked' => $currentUserReaction === 'like',
                'is_disliked' => $currentUserReaction === 'dislike',
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
            'shop_id' => ['required', 'integer'],
            'rating' => ['required', 'numeric', 'min:0.5', 'max:5'],
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
