<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemController extends Controller
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

    public function index()
    {
        $items = Item::with(['shop', 'category'])->get();

        return response()->json([
            'status' => true,
            'message' => 'Items retrieved successfully',
            'data' => $items,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_id' => ['required', 'integer', 'exists:shop,shop_id'],
            'item_name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'item_image' => ['nullable'],
            'category_id' => ['required', 'integer', 'exists:item_category,category_id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('item_image')) {
            $imagePath = $this->uploadImage($request->file('item_image'));
        } else if ($request->filled('item_image')) {
            $imagePath = $request->input('item_image');
        }

        $data = $request->only(['shop_id', 'item_name', 'price', 'description', 'category_id']);
        if ($imagePath) {
            $data['item_image'] = $imagePath;
        }

        $item = Item::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Item created successfully',
            'data' => $item,
        ], 201);
    }

    public function show($id)
    {
        $item = Item::with(['shop', 'category'])->find($id);

        if (! $item) {
            return response()->json([
                'status' => false,
                'message' => 'Item not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Item retrieved successfully',
            'data' => $item,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $item = Item::find($id);

        if (! $item) {
            return response()->json([
                'status' => false,
                'message' => 'Item not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'shop_id' => ['sometimes', 'integer', 'exists:shop,shop_id'],
            'item_name' => ['sometimes', 'string', 'max:100'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'item_image' => ['nullable'],
            'category_id' => ['sometimes', 'integer', 'exists:item_category,category_id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['shop_id', 'item_name', 'price', 'description', 'category_id']);
        if ($request->hasFile('item_image')) {
            $data['item_image'] = $this->uploadImage($request->file('item_image'), $item->item_image);
        } else if ($request->filled('item_image')) {
            $data['item_image'] = $request->input('item_image');
        }

        $item->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Item updated successfully',
            'data' => $item->fresh(),
        ], 200);
    }

    public function destroy($id)
    {
        $item = Item::find($id);

        if (! $item) {
            return response()->json([
                'status' => false,
                'message' => 'Item not found',
                'data' => null,
            ], 404);
        }

        if ($item->item_image) {
            \Illuminate\Support\Facades\Storage::disk('custom_images')->delete($item->item_image);
        }

        $item->delete();

        return response()->json([
            'status' => true,
            'message' => 'Item deleted successfully',
            'data' => null,
        ], 200);
    }
}
