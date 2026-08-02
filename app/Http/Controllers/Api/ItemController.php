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
        if ($oldImage && file_exists(storage_path('images/' . $oldImage))) {
            @unlink(storage_path('images/' . $oldImage));
        }

        $ext = $file->getClientOriginalExtension() ?: 'png';
        $filename = time() . '_item_' . uniqid() . '.' . $ext;
        $file->move(storage_path('images'), $filename);

        return $filename;
    }

    public function index(Request $request)
    {
        $query = Item::with(['shop', 'category']);

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->input('shop_id'));
        }

        $items = $query->get();

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
            'item_image_file' => ['nullable'],
            'images' => ['nullable'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['nullable', 'in:เปิดขาย,ปิดขาย'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'data' => $validator->errors(),
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('item_image_file')) {
            $imagePath = $this->uploadImage($request->file('item_image_file'));
        } else if ($request->hasFile('item_image')) {
            $imagePath = $this->uploadImage($request->file('item_image'));
        } else if ($request->filled('item_image')) {
            $imagePath = $request->input('item_image');
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
                $imagesList = is_array($decoded) ? $decoded : array_map('trim', explode(',', $inputImages));
            } else if (is_array($inputImages)) {
                $imagesList = $inputImages;
            }
        }

        if (empty($imagesList) && $imagePath) {
            $imagesList = [$imagePath];
        }

        $data = $request->only(['shop_id', 'item_name', 'price', 'description', 'category_id', 'status']);
        if (!isset($data['category_id']) || $data['category_id'] === null || $data['category_id'] === '') {
            $data['category_id'] = 1;
        }
        if (empty($data['status'])) {
            $data['status'] = 'เปิดขาย';
        }
        if ($imagePath) {
            $data['item_image'] = $imagePath;
        }
        if (!empty($imagesList)) {
            $data['images'] = $imagesList;
            if (empty($data['item_image'])) {
                $data['item_image'] = $imagesList[0];
            }
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
            'item_image_file' => ['nullable'],
            'images' => ['nullable'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['nullable', 'in:เปิดขาย,ปิดขาย'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'data' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['shop_id', 'item_name', 'price', 'description', 'category_id', 'status']);
        if ($request->hasFile('item_image_file')) {
            $data['item_image'] = $this->uploadImage($request->file('item_image_file'), $item->item_image);
        } else if ($request->hasFile('item_image')) {
            $data['item_image'] = $this->uploadImage($request->file('item_image'), $item->item_image);
        } else if ($request->filled('item_image')) {
            $data['item_image'] = $request->input('item_image');
        }

        $imageFiles = $request->hasFile('images')
            ? $request->file('images')
            : ($request->hasFile('images[]') ? $request->file('images[]') : null);

        if ($imageFiles) {
            $imagesList = [];
            foreach ((array) $imageFiles as $file) {
                $imagesList[] = $this->uploadImage($file);
            }
            $data['images'] = $imagesList;
        } else if ($request->filled('images')) {
            $inputImages = $request->input('images');
            if (is_string($inputImages)) {
                $decoded = json_decode($inputImages, true);
                $data['images'] = is_array($decoded) ? $decoded : array_map('trim', explode(',', $inputImages));
            } else if (is_array($inputImages)) {
                $data['images'] = $inputImages;
            }
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
