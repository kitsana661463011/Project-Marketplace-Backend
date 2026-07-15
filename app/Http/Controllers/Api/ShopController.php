<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    private function uploadImage($file, $oldImage = null)
    {
        if ($oldImage) {
            \Illuminate\Support\Facades\Storage::disk('custom_images')->delete($oldImage);
        }

        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('', $filename, 'custom_images');

        return $filename;
    }

    public function index(Request $request)
    {
        $query = Shop::with(['category', 'owner']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $shops = $query->get();

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
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
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

        $data = $request->only(['shop_name', 'category_id', 'description', 'shop_phone', 'social_links', 'user_id']);
        if ($imagePath) {
            $data['shop_image'] = $imagePath;
        }

        $shop = Shop::create($data);

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
            'shop_phone' => ['nullable', 'string', 'max:15'],
            'social_links' => ['nullable', 'json'],
            'shop_image' => ['nullable'],
            'user_id' => ['sometimes', 'integer', 'exists:user,user_id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['shop_name', 'category_id', 'description', 'shop_phone', 'social_links', 'user_id']);
        if ($request->hasFile('shop_image')) {
            $data['shop_image'] = $this->uploadImage($request->file('shop_image'), $shop->shop_image);
        } else if ($request->filled('shop_image')) {
            $data['shop_image'] = $request->input('shop_image');
        }

        $shop->update($data);

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

        if ($shop->shop_image) {
            \Illuminate\Support\Facades\Storage::disk('custom_images')->delete($shop->shop_image);
        }

        $shop->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shop deleted successfully',
            'data' => null,
        ], 200);
    }
}
