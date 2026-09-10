<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShopController extends Controller
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
        $filename = 'shop_main_' . time() . '_' . uniqid() . '.' . $ext;
        $file->move(storage_path('images'), $filename);

        return $filename;
    }

    private function syncShopTags($shopId, $tagsRaw)
    {
        if ($tagsRaw === null) return;
        DB::table('shop_has_tag')->where('shop_id', $shopId)->delete();

        $tagNames = is_array($tagsRaw)
            ? $tagsRaw
            : array_map('trim', explode(',', (string)$tagsRaw));

        foreach ($tagNames as $name) {
            if (empty($name)) continue;
            $opt = DB::table('user_interest_option')
                ->where('interest_name', $name)
                ->orWhere('interest_id', $name)
                ->first();
            if ($opt) {
                DB::table('shop_has_tag')->insertOrIgnore([
                    'shop_id' => $shopId,
                    'interest_id' => $opt->interest_id,
                ]);
            }
        }
    }

    public function index(Request $request)
    {
        $query = Shop::with(['category', 'owner']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $shops = $query->get()->map(function ($shop) {
            $avgRating = $shop->reviews()->where('status', 'show')->avg('rating');
            $reviewCount = $shop->reviews()->where('status', 'show')->count();
            $followsCount = $shop->follows()->count();

            $activeBooking = DB::table('stall_booking as sb')
                ->join('stall as s', 's.stall_id', '=', 'sb.stall_id')
                ->leftJoin('market_zone as mz', 'mz.zone_id', '=', 's.zone_id')
                ->where('sb.user_id', $shop->user_id)
                ->where('sb.status', 'approved')
                ->select('s.stall_number', 'mz.zone_name')
                ->first();

            $shopArray = $shop->toArray();
            $shopArray['avg_rating'] = $avgRating ? round((float)$avgRating, 1) : null;
            $shopArray['review_count'] = $reviewCount;
            $shopArray['follows_count'] = $followsCount;
            $shopArray['stall_number'] = $activeBooking ? $activeBooking->stall_number : null;
            $shopArray['zone_name'] = $activeBooking ? $activeBooking->zone_name : null;
            $shopArray['tags'] = $shop->tags;
            return $shopArray;
        });

        return response()->json([
            'status' => true,
            'message' => 'Shops retrieved successfully',
            'data' => $shops,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_name' => ['required', 'string', 'max:100'],
            'category_id' => ['required', 'integer', 'exists:shop_category,category_id'],
            'description' => ['nullable', 'string'],
            'shop_phone' => ['nullable', 'string', 'max:15'],
            'social_links' => ['nullable', 'json'],
            'shop_image' => ['nullable'],
            'status' => ['nullable', 'string', 'max:50'],
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
            'tags' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('shop_image')) {
            $imagePath = $this->uploadImage($request->file('shop_image'));
        } else if ($request->filled('shop_image')) {
            $imagePath = $request->input('shop_image');
        }

        $data = $request->only(['shop_name', 'category_id', 'description', 'shop_phone', 'social_links', 'user_id', 'status']);
        if ($imagePath) {
            $data['shop_image'] = $imagePath;
        }

        $shop = Shop::create($data);

        if ($request->has('tags')) {
            $this->syncShopTags($shop->shop_id, $request->input('tags'));
        }

        $shopArray = $shop->fresh()->toArray();
        $shopArray['tags'] = $shop->tags;

        return response()->json([
            'status' => true,
            'message' => 'Shop created successfully',
            'data' => $shopArray,
        ], 201);
    }

    public function show($id)
    {
        $shop = Shop::with(['category', 'owner'])->withCount('follows')->find($id);

        if (! $shop) {
            return response()->json([
                'status' => false,
                'message' => 'Shop not found',
                'data' => null,
            ], 404);
        }

        $shopArray = $shop->toArray();
        $shopArray['tags'] = $shop->tags;

        return response()->json([
            'status' => true,
            'message' => 'Shop retrieved successfully',
            'data' => $shopArray,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $shop = Shop::find($id);

        if (! $shop) {
            return response()->json([
                'status' => false,
                'message' => 'Shop not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'shop_name' => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', 'integer', 'exists:shop_category,category_id'],
            'description' => ['nullable', 'string'],
            'shop_phone' => ['nullable', 'string', 'max:15'],
            'social_links' => ['nullable', 'json'],
            'shop_image' => ['nullable'],
            'status' => ['nullable', 'string', 'max:50'],
            'user_id' => ['sometimes', 'integer', 'exists:user,user_id'],
            'tags' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['shop_name', 'category_id', 'description', 'shop_phone', 'social_links', 'user_id', 'status']);
        if ($request->hasFile('shop_image_file')) {
            $data['shop_image'] = $this->uploadImage($request->file('shop_image_file'), $shop->shop_image);
        } else if ($request->hasFile('shop_image')) {
            $data['shop_image'] = $this->uploadImage($request->file('shop_image'), $shop->shop_image);
        } else if ($request->filled('shop_image')) {
            $data['shop_image'] = $request->input('shop_image');
        }

        $shop->update($data);

        if ($request->has('tags')) {
            $this->syncShopTags($shop->shop_id, $request->input('tags'));
        }

        $shopArray = $shop->fresh()->toArray();
        $shopArray['tags'] = $shop->tags;

        return response()->json([
            'status' => true,
            'message' => 'Shop updated successfully',
            'data' => $shopArray,
        ], 200);
    }

    public function destroy($id)
    {
        $shop = Shop::find($id);

        if (! $shop) {
            return response()->json([
                'status' => false,
                'message' => 'Shop not found',
                'data' => null,
            ], 404);
        }

        if ($shop->shop_image) {
            \Illuminate\Support\Facades\Storage::disk('custom_images')->delete($shop->shop_image);
            if (file_exists(storage_path('images/' . $shop->shop_image))) {
                @unlink(storage_path('images/' . $shop->shop_image));
            }
        }

        $shop->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shop deleted successfully',
            'data' => null,
        ], 200);
    }
}
