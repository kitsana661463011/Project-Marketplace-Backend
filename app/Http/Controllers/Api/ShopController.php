<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    public function index()
    {
        $shops = Shop::with(['category', 'owner'])->get();

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
            'shop_image' => ['nullable', 'string', 'max:255'],
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $shop = Shop::create($request->only(['shop_name', 'category_id', 'description', 'shop_image', 'user_id']));

        return response()->json([
            'status' => true,
            'message' => 'Shop created successfully',
            'data' => $shop,
        ], 201);
    }

    public function show($id)
    {
        $shop = Shop::with(['category', 'owner'])->find($id);

        if (! $shop) {
            return response()->json([
                'status' => false,
                'message' => 'Shop not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shop retrieved successfully',
            'data' => $shop,
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
            'shop_image' => ['nullable', 'string', 'max:255'],
            'user_id' => ['sometimes', 'integer', 'exists:user,user_id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $shop->update($request->only(['shop_name', 'category_id', 'description', 'shop_image', 'user_id']));

        return response()->json([
            'status' => true,
            'message' => 'Shop updated successfully',
            'data' => $shop->fresh(),
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

        $shop->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shop deleted successfully',
            'data' => null,
        ], 200);
    }
}
