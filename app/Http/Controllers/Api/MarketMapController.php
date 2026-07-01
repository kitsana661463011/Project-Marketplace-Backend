<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketMap;
use App\Models\MarketMapItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MarketMapController extends Controller
{
    /**
     * Retrieve the floor plan details along with the positions of elements.
     */
    public function show($id)
    {
        $map = MarketMap::with(['items.stall.bookings.user'])->find($id);

        if (!$map) {
            return response()->json([
                'status' => false,
                'message' => 'Market map not found',
            ], 404);
        }

        $items = $map->items->map(function ($item) {
            $seller = null;
            if ($item->stall) {
                // Find the latest approved or active booking
                $activeBooking = $item->stall->bookings
                    ->sortByDesc('booking_id')
                    ->first();

                if ($activeBooking && $activeBooking->user) {
                    $seller = [
                        'id'    => (string)$activeBooking->user->user_id,
                        'name'  => $activeBooking->user->username,
                        'phone' => $activeBooking->user->phone ?: '',
                    ];
                }
            }

            return [
                'map_item_id' => (string)$item->map_item_id,
                'item_type'   => $item->item_type,
                'stall_id'    => $item->stall_id,
                'zone_id'     => $item->zone_id,
                'label'       => $item->label,
                'x'           => (int)$item->x,
                'y'           => (int)$item->y,
                'width'       => (int)$item->width,
                'height'      => (int)$item->height,
                'fill_color'  => $item->fill_color,
                'rotation'    => (int)$item->rotation,
                'z_index'     => (int)$item->z_index,
                'status'      => $item->stall ? $item->stall->status : 'available',
                'seller'      => $seller,
            ];
        });

        $zones = \App\Models\MarketZone::all()->map(function ($zone) {
            return [
                'zone_id'    => $zone->zone_id,
                'zone_name'  => $zone->zone_name,
                'zone_price' => (float)$zone->zone_price,
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => [
                'map_id'     => $map->map_id,
                'map_name'   => $map->map_name,
                'map_width'  => (int)$map->map_width,
                'map_height' => (int)$map->map_height,
                'items'      => $items,
                'zones'      => $zones,
            ],
        ], 200);
    }

    /**
     * Save the entire floor plan layout via batch transaction.
     */
    public function saveItems(Request $request, $id)
    {
        $map = MarketMap::find($id);
        if (!$map) {
            return response()->json([
                'status' => false,
                'message' => 'Market map not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'items'               => 'required|array',
            'items.*.map_item_id' => 'required',
            'items.*.item_type'   => 'required|in:block,road,zone,entrance,toilet',
            'items.*.x'           => 'required|numeric',
            'items.*.y'           => 'required|numeric',
            'items.*.width'       => 'required|numeric',
            'items.*.height'      => 'required|numeric',
            'items.*.rotation'    => 'nullable|numeric',
            'items.*.fill_color'  => 'nullable|string|max:20',
            'items.*.stall_id'    => 'nullable|integer',
            'items.*.zone_id'     => 'nullable|integer',
            'items.*.label'       => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $itemsData = $request->input('items');

        try {
            DB::transaction(function () use ($map, $itemsData) {
                // Delete existing items that are not in the new layout array
                // Only keep track of numeric map_item_ids for deletion to avoid SQL casting exceptions
                $existingItemIds = collect($itemsData)
                    ->pluck('map_item_id')
                    ->filter(function($id) {
                        return is_numeric($id) && !str_starts_with((string)$id, 'new-');
                    })
                    ->map(function($id) {
                        return (int)$id;
                    })
                    ->toArray();

                MarketMapItem::where('map_id', $map->map_id)
                    ->whereNotIn('map_item_id', $existingItemIds)
                    ->delete();

                // Save or Update items
                foreach ($itemsData as $item) {
                    $itemId = $item['map_item_id'];
                    $isNew = !is_numeric($itemId) || str_starts_with((string)$itemId, 'new-');

                    if ($isNew) {
                        MarketMapItem::create([
                            'map_id'     => $map->map_id,
                            'item_type'  => $item['item_type'],
                            'stall_id'   => $item['stall_id'] ?? null,
                            'zone_id'    => $item['zone_id'] ?? null,
                            'label'      => $item['label'] ?? '',
                            'x'          => (int)$item['x'],
                            'y'          => (int)$item['y'],
                            'width'      => (int)$item['width'],
                            'height'     => (int)$item['height'],
                            'rotation'   => (int)($item['rotation'] ?? 0),
                            'fill_color' => $item['fill_color'] ?? '#5d8aff',
                        ]);
                    } else {
                        MarketMapItem::where('map_id', $map->map_id)
                            ->where('map_item_id', (int)$itemId)
                            ->update([
                                'item_type'  => $item['item_type'],
                                'stall_id'   => $item['stall_id'] ?? null,
                                'zone_id'    => $item['zone_id'] ?? null,
                                'label'      => $item['label'] ?? '',
                                'x'          => (int)$item['x'],
                                'y'          => (int)$item['y'],
                                'width'      => (int)$item['width'],
                                'height'     => (int)$item['height'],
                                'rotation'   => (int)($item['rotation'] ?? 0),
                                'fill_color' => $item['fill_color'] ?? '#5d8aff',
                            ]);
                    }
                }
            });

            return response()->json([
                'status'  => true,
                'message' => 'Market floor plan items saved successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to save items',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
