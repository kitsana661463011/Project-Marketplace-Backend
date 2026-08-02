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
        $map = MarketMap::with(['items.stall.bookings.user.shop.category'])->find($id);

        if (!$map) {
            return response()->json([
                'status' => false,
                'message' => 'Market map not found',
            ], 404);
        }

        $items = $map->items->map(function ($item) {
            $seller = null;
            $mapStatus = 'available'; // default

            if ($item->stall) {
                // Derive stall map status from stall DB status first
                $stalledStatus = $item->stall->status;
                if ($stalledStatus === 'maintenance') {
                    $mapStatus = 'repair';
                } elseif ($stalledStatus === 'occupied') {
                    $mapStatus = 'occupied';
                } else {
                    $mapStatus = $stalledStatus ?: 'available';
                }

                // Find active booking: prefer approved, then pending, then latest
                $bookings = $item->stall->bookings->sortByDesc('booking_id');
                $activeBooking = $bookings->first(fn($b) => in_array($b->status, ['approved', 'occupied']))
                    ?? $bookings->first(fn($b) => $b->status === 'pending')
                    ?? $bookings->first();

                if ($activeBooking && $activeBooking->user) {
                    $shop = $activeBooking->user->shop;
                    $shopName = $shop ? $shop->shop_name : null;
                    $displayName = $shopName ?: 'ร้านค้าจองแล้ว';

                    $seller = [
                        'id'             => (string)$activeBooking->user->user_id,
                        'name'           => $displayName,
                        'shop_id'        => $shop ? $shop->shop_id : null,
                        'shop_name'      => $shopName,
                        'category_name'  => ($shop && $shop->category) ? $shop->category->category_name : 'อาหารและเครื่องดื่ม',
                        'description'    => $shop ? $shop->description : '',
                        'shop_phone'     => ($shop && $shop->shop_phone) ? $shop->shop_phone : ($activeBooking->user->phone ?: ''),
                        'shop_image'     => $shop ? $shop->shop_image : null,
                        'user_name'      => $activeBooking->user->username,
                        'phone'          => $activeBooking->user->phone ?: '',
                        'start_date'     => $activeBooking->start_date ? (string)$activeBooking->start_date : null,
                        'end_date'       => $activeBooking->end_date ? (string)$activeBooking->end_date : null,
                        'booking_id'     => $activeBooking->booking_id,
                        'booking_status' => $activeBooking->status,
                    ];

                    // Override map status based on actual booking status
                    if (in_array($activeBooking->status, ['approved', 'occupied'])) {
                        $mapStatus = 'approved';
                    } elseif ($activeBooking->status === 'pending') {
                        $mapStatus = 'occupied'; // show as occupied while pending
                    } elseif (in_array($activeBooking->status, ['refund_requested', 'refunded'], true)) {
                        $mapStatus = $activeBooking->status;
                    }
                    // If booking is rejected/cancelled, keep the stall's own status
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
                'size'             => $item->stall ? ($item->stall->size ?: '3x3 เมตร') : '3x3 เมตร',
                'price'            => $item->stall ? (float)($item->stall->price ?? 500) : 500,
                'rental_type'      => $item->stall ? ($item->stall->rental_type ?: 'daily') : 'daily',
                'daily_price'      => $item->stall ? ($item->stall->daily_price !== null ? (float)$item->stall->daily_price : (float)($item->stall->price ?? 500)) : 500,
                'monthly_price'    => $item->stall ? ($item->stall->monthly_price !== null ? (float)$item->stall->monthly_price : null) : null,
                'entry_fee'        => $item->stall ? ($item->stall->entry_fee !== null ? (float)$item->stall->entry_fee : null) : null,
                'security_deposit' => $item->stall ? ($item->stall->security_deposit !== null ? (float)$item->stall->security_deposit : null) : null,
                'status'           => $mapStatus,
                'seller'           => $seller,
            ];
        });

        $zones = \App\Models\MarketZone::all()->map(function ($zone) {
            return [
                'zone_id'   => $zone->zone_id,
                'zone_name' => $zone->zone_name,
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
            'items'                    => 'required|array',
            'items.*.map_item_id'      => 'required',
            'items.*.item_type'        => 'required|in:block,road,zone,entrance,toilet,exit,dining,parking,info,trash',
            'items.*.x'                => 'required|numeric',
            'items.*.y'                => 'required|numeric',
            'items.*.width'            => 'required|numeric',
            'items.*.height'           => 'required|numeric',
            'items.*.rotation'         => 'nullable|numeric',
            'items.*.fill_color'       => 'nullable|string|max:20',
            'items.*.stall_id'         => 'nullable',
            'items.*.zone_id'          => 'nullable',
            'items.*.label'            => 'nullable|string|max:100',
            'items.*.size'             => 'nullable|string|max:50',
            'items.*.price'            => 'nullable|numeric',
            'items.*.rental_type'      => 'nullable|in:daily,monthly',
            'items.*.daily_price'      => 'nullable|numeric',
            'items.*.monthly_price'    => 'nullable|numeric',
            'items.*.entry_fee'        => 'nullable|numeric',
            'items.*.security_deposit' => 'nullable|numeric',
            'items.*.status'           => 'nullable|string|max:20',
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

                    $stallId = (isset($item['stall_id']) && is_numeric($item['stall_id'])) ? (int)$item['stall_id'] : null;
                    $zoneId = (isset($item['zone_id']) && is_numeric($item['zone_id'])) ? (int)$item['zone_id'] : null;

                    if ($item['item_type'] === 'zone') {
                        $zoneName = trim($item['label'] ?? '');
                        if (!empty($zoneName)) {
                            $existingZone = \App\Models\MarketZone::where('zone_name', $zoneName)->first();
                            if ($existingZone) {
                                $zoneId = $existingZone->zone_id;
                            } else {
                                $newZone = \App\Models\MarketZone::create([
                                    'zone_name' => $zoneName,
                                ]);
                                $zoneId = $newZone->zone_id;
                            }
                        }
                    }

                    if ($item['item_type'] === 'block') {
                        $rentalType = $item['rental_type'] ?? 'daily';
                        $dailyPrice = isset($item['daily_price']) && $item['daily_price'] !== null ? (float)$item['daily_price'] : ($rentalType === 'daily' ? (float)($item['price'] ?? 500) : null);
                        $monthlyPrice = isset($item['monthly_price']) && $item['monthly_price'] !== null ? (float)$item['monthly_price'] : ($rentalType === 'monthly' ? (float)($item['price'] ?? 5000) : null);
                        $entryFee = isset($item['entry_fee']) && $item['entry_fee'] !== null ? (float)$item['entry_fee'] : null;
                        $securityDeposit = isset($item['security_deposit']) && $item['security_deposit'] !== null ? (float)$item['security_deposit'] : null;

                        $stallPayload = [
                            'size'             => $item['size'] ?? '3x3 เมตร',
                            'price'            => $dailyPrice ?: ($monthlyPrice ?: 500.00),
                            'rental_type'      => $rentalType,
                            'daily_price'      => $rentalType === 'daily' ? $dailyPrice : null,
                            'monthly_price'    => $rentalType === 'monthly' ? $monthlyPrice : null,
                            'entry_fee'        => $rentalType === 'monthly' ? $entryFee : null,
                            'security_deposit' => $rentalType === 'monthly' ? $securityDeposit : null,
                            'status'           => ($item['status'] ?? 'available') === 'repair' ? 'maintenance' : ($item['status'] ?? 'available'),
                        ];

                        // Check if we need to create a new Stall in database
                        if (!$stallId) {
                            if (!$zoneId) {
                                $zoneId = \App\Models\MarketZone::first()?->zone_id ?? 1;
                            }
                            $stallPayload['zone_id'] = $zoneId;

                            // Check if a stall with the same number already exists to prevent duplicates
                            $existingStall = \App\Models\Stall::where('stall_number', $item['label'])->first();
                            if ($existingStall) {
                                $existingStall->update($stallPayload);
                                $stallId = $existingStall->stall_id;
                            } else {
                                $stallPayload['stall_number'] = $item['label'] ?? ('STALL-' . uniqid());
                                $newStall = \App\Models\Stall::create($stallPayload);
                                $stallId = $newStall->stall_id;
                            }
                        } else {
                            // Update existing stall properties in stall table
                            $stall = \App\Models\Stall::find($stallId);
                            if ($stall) {
                                if ($zoneId) {
                                    $stallPayload['zone_id'] = $zoneId;
                                }
                                $stallPayload['stall_number'] = $item['label'] ?? $stall->stall_number;
                                $stall->update($stallPayload);
                            }
                        }
                    }

                    if ($isNew) {
                        MarketMapItem::create([
                            'map_id'     => $map->map_id,
                            'item_type'  => $item['item_type'],
                            'stall_id'   => $stallId,
                            'zone_id'    => $zoneId,
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
                                'stall_id'   => $stallId,
                                'zone_id'    => $zoneId,
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
