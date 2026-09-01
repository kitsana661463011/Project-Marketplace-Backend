<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemCategoryController extends Controller
{
    /**
     * Display a listing of item categories isolated by shop.
     */
    public function index(Request $request)
    {
        $shopId = $request->query('shop_id');

        $categoriesQuery = ItemCategory::query();

        if ($shopId) {
            $shopId = (int) $shopId;
            // Get categories that belong to this shop OR contain items belonging to this shop
            $categoriesQuery->where(function ($q) use ($shopId) {
                $q->where('shop_id', $shopId)
                  ->orWhereHas('items', function ($itemQ) use ($shopId) {
                      $itemQ->where('shop_id', $shopId);
                  });
            });

            $categoriesQuery->withCount(['items' => function ($q) use ($shopId) {
                $q->where('shop_id', $shopId);
            }]);
        } else {
            $categoriesQuery->withCount('items');
        }

        $categories = $categoriesQuery->orderBy('category_name')->get();

        return response()->json([
            'status' => true,
            'message' => 'Item categories retrieved successfully',
            'data' => $categories,
        ], 200);
    }

    /**
     * Store a newly created item category specifically for a shop.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_name' => ['required', 'string', 'max:100'],
            'shop_id' => ['nullable', 'integer'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'data' => $validator->errors(),
            ], 422);
        }

        $name = trim($request->input('category_name'));
        $shopId = $request->filled('shop_id') ? (int) $request->input('shop_id') : null;

        // Check if category exists for this shop specifically
        if ($shopId) {
            $category = ItemCategory::where('shop_id', $shopId)
                ->where('category_name', $name)
                ->first();

            if (!$category) {
                $category = ItemCategory::create([
                    'category_name' => $name,
                    'shop_id' => $shopId,
                ]);
            }
        } else {
            $category = ItemCategory::firstOrCreate(
                ['category_name' => $name, 'shop_id' => null]
            );
        }

        // If item IDs are provided, update their category_id
        $itemIds = $request->input('item_ids');
        if (!empty($itemIds) && is_array($itemIds)) {
            $query = Item::whereIn('item_id', $itemIds);
            if ($shopId) {
                $query->where('shop_id', $shopId);
            }
            $query->update(['category_id' => $category->category_id]);
        }

        return response()->json([
            'status' => true,
            'message' => 'ประเภทสินค้าถูกบันทึกเรียบร้อยแล้ว',
            'data' => $category,
        ], 201);
    }

    /**
     * Update the specified item category in storage.
     */
    public function update(Request $request, $id)
    {
        $category = ItemCategory::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่พบประเภทสินค้านี้',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'category_name' => ['required', 'string', 'max:100'],
            'shop_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'data' => $validator->errors(),
            ], 422);
        }

        $shopId = $request->input('shop_id');
        if ($shopId && $category->shop_id && (int)$category->shop_id !== (int)$shopId) {
            return response()->json([
                'status' => false,
                'message' => 'คุณไม่มีสิทธิ์แก้ไขหมวดหมู่นี้',
                'data' => null,
            ], 403);
        }

        $category->category_name = trim($request->input('category_name'));
        if ($shopId && !$category->shop_id) {
            $category->shop_id = (int)$shopId;
        }
        $category->save();

        return response()->json([
            'status' => true,
            'message' => 'แก้ไขชื่อประเภทสินค้าสำเร็จแล้ว',
            'data' => $category,
        ], 200);
    }

    /**
     * Assign items to a category.
     */
    public function assignItems(Request $request, $id)
    {
        $category = ItemCategory::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่พบประเภทสินค้านี้',
                'data' => null,
            ], 404);
        }

        $itemIds = $request->input('item_ids', []);
        $shopId = $request->input('shop_id');

        if (!empty($itemIds) && is_array($itemIds)) {
            $query = Item::whereIn('item_id', $itemIds);
            if ($shopId) {
                $query->where('shop_id', $shopId);
            }
            $query->update(['category_id' => $id]);
        }

        return response()->json([
            'status' => true,
            'message' => 'เพิ่มสินค้าเข้าประเภทนี้สำเร็จแล้ว',
            'data' => null,
        ], 200);
    }

    /**
     * Remove items from a category.
     */
    public function removeItems(Request $request, $id)
    {
        $itemIds = $request->input('item_ids', []);
        $shopId = $request->input('shop_id');

        // Fallback category id
        $defaultCat = ItemCategory::where('category_id', '!=', $id);
        if ($shopId) {
            $defaultCat->where('shop_id', $shopId);
        }
        $defaultCat = $defaultCat->first();
        $defaultId = $defaultCat ? $defaultCat->category_id : 1;

        if (!empty($itemIds) && is_array($itemIds)) {
            $query = Item::whereIn('item_id', $itemIds)->where('category_id', $id);
            if ($shopId) {
                $query->where('shop_id', $shopId);
            }
            $query->update(['category_id' => $defaultId]);
        }

        return response()->json([
            'status' => true,
            'message' => 'นำสินค้าออกจากประเภทนี้สำเร็จแล้ว',
            'data' => null,
        ], 200);
    }

    /**
     * Remove the specified item category from storage.
     */
    public function destroy(Request $request, $id)
    {
        $category = ItemCategory::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'ไม่พบประเภทสินค้านี้',
                'data' => null,
            ], 404);
        }

        $shopId = $request->query('shop_id') ?? $request->input('shop_id');

        // Check ownership if category has shop_id
        if ($shopId && $category->shop_id && (int)$category->shop_id !== (int)$shopId) {
            return response()->json([
                'status' => false,
                'message' => 'คุณไม่มีสิทธิ์ลบหมวดหมู่นี้',
                'data' => null,
            ], 403);
        }

        // Reassign items of this shop or all items if global
        $defaultCat = ItemCategory::where('category_id', '!=', $id);
        if ($shopId) {
            $defaultCat->where('shop_id', $shopId);
        }
        $defaultCat = $defaultCat->first();
        $defaultId = $defaultCat ? $defaultCat->category_id : 1;

        if ($shopId) {
            Item::where('shop_id', $shopId)
                ->where('category_id', $id)
                ->update(['category_id' => $defaultId]);
        } else {
            Item::where('category_id', $id)
                ->update(['category_id' => $defaultId]);
        }

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'ลบประเภทสินค้าสำเร็จแล้ว',
            'data' => null,
        ], 200);
    }
}
