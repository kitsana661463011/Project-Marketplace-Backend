<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowShop;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FollowShopController extends Controller
{
    /**
     * Get list of shops followed by a specific user.
     */
    public function index(Request $request)
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID is required',
                'data' => [],
            ], 400);
        }

        $followed = FollowShop::with(['shop.category', 'shop.owner'])
            ->where('user_id', $userId)
            ->orderBy('follow_date', 'desc')
            ->get();

        $shops = $followed->map(function ($item) {
            if ($item->shop) {
                $item->shop->follow_date = $item->follow_date;
                $item->shop->is_followed = true;
                return $item->shop;
            }
            return null;
        })->filter()->values();

        return response()->json([
            'status' => true,
            'message' => 'Followed shops retrieved successfully',
            'data' => $shops,
        ], 200);
    }

    /**
     * Toggle follow/unfollow status for a shop.
     */
    public function toggle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:user,user_id',
            'shop_id' => 'required|integer|exists:shop,shop_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->input('user_id');
        $shopId = $request->input('shop_id');

        $existing = FollowShop::where('user_id', $userId)
            ->where('shop_id', $shopId)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'status' => true,
                'is_following' => false,
                'message' => 'ยกเลิกการติดตามเรียบร้อยแล้ว',
            ], 200);
        } else {
            $follow = FollowShop::create([
                'user_id' => $userId,
                'shop_id' => $shopId,
                'follow_date' => now(),
            ]);

            return response()->json([
                'status' => true,
                'is_following' => true,
                'message' => 'ติดตามร้านค้าเรียบร้อยแล้ว',
                'data' => $follow,
            ], 201);
        }
    }

    /**
     * Check if user is following a shop.
     */
    public function check(Request $request)
    {
        $userId = $request->query('user_id');
        $shopId = $request->query('shop_id');

        if (!$userId || !$shopId) {
            return response()->json(['is_following' => false]);
        }

        $isFollowing = FollowShop::where('user_id', $userId)
            ->where('shop_id', $shopId)
            ->exists();

        return response()->json([
            'status' => true,
            'is_following' => $isFollowing,
        ]);
    }
}
