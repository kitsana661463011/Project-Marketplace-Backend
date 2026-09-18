<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = Announcement::query()->with('user');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            if ($filter === 'active') {
                $query->activeRange();
            } elseif ($filter === 'history' || $filter === 'expired') {
                $query->expired();
            } elseif ($filter === 'scheduled') {
                $query->scheduled();
            }
        }

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $cat = $request->input('category');
            if ($cat === 'event') $cat = 'activity';
            $query->where('announcement_type', $cat);
        }

        $userId = $request->input('user_id') ?: $request->user()?->user_id;
        $readIds = $userId ? \App\Models\UserAnnouncementRead::where('user_id', $userId)->pluck('announcement_id')->toArray() : null;

        $announcements = $query
            ->orderByRaw("CASE WHEN announcement_type = 'urgent' THEN 0 WHEN announcement_type = 'activity' THEN 1 ELSE 2 END")
            ->orderByDesc('publish_date')
            ->get()->map(function (Announcement $announcement) use ($readIds) {
            return $this->formatAnnouncement($announcement, $readIds);
        });

        return response()->json([
            'status' => true,
            'message' => 'Announcements retrieved successfully',
            'data' => $announcements,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:100'],
            'announcement_type' => ['required', 'in:urgent,activity,general'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable'],
            'publish_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:publish_date'],
            'status' => ['nullable', 'in:active,inactive'],
            'user_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'announcement_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists(storage_path('images'))) {
                @mkdir(storage_path('images'), 0777, true);
            }
            $file->storeAs('', $filename, 'custom_images');
            $imagePath = $filename;
        } elseif ($request->filled('image')) {
            $rawImg = $request->input('image');
            $imagePath = basename($rawImg);
        }

        $createData = [
            'title' => $request->input('title'),
            'announcement_type' => $request->input('announcement_type'),
            'description' => $request->input('description'),
            'publish_date' => $request->filled('publish_date') ? $request->input('publish_date') : now(),
            'end_date' => $request->filled('end_date') ? $request->input('end_date') : null,
            'status' => $request->input('status', 'active'),
            'user_id' => $request->input('user_id', 1),
        ];

        if ($imagePath !== null) {
            $createData['image'] = $imagePath;
        }

        $announcement = Announcement::create($createData);

        return response()->json([
            'status' => true,
            'message' => 'Announcement created successfully',
            'data' => $this->formatAnnouncement($announcement),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'max:100'],
            'announcement_type' => ['sometimes', 'in:urgent,activity,general'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable'],
            'publish_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $announcement = Announcement::find($id);

        if (! $announcement) {
            return response()->json([
                'status' => false,
                'message' => 'Announcement not found',
                'data' => null,
            ], 404);
        }

        $announcement->fill($request->only(['title', 'announcement_type', 'description', 'status']));

        if ($request->has('publish_date')) {
            $announcement->publish_date = $request->input('publish_date') ?: now();
        }
        if ($request->has('end_date')) {
            $announcement->end_date = $request->input('end_date') ?: null;
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            if ($announcement->image && !str_starts_with($announcement->image, 'http')) {
                \Illuminate\Support\Facades\Storage::disk('custom_images')->delete($announcement->image);
                if (file_exists(storage_path('images/' . $announcement->image))) {
                    @unlink(storage_path('images/' . $announcement->image));
                }
            }
            $file = $request->file('image');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'announcement_' . time() . '_' . uniqid() . '.' . $ext;
            if (!file_exists(storage_path('images'))) {
                @mkdir(storage_path('images'), 0777, true);
            }
            $file->storeAs('', $filename, 'custom_images');
            $imagePath = $filename;
        } elseif ($request->filled('image')) {
            $rawImg = $request->input('image');
            $imagePath = basename($rawImg);
        }

        if ($imagePath !== null) {
            $announcement->image = $imagePath;
        }

        $announcement->save();

        return response()->json([
            'status' => true,
            'message' => 'Announcement updated successfully',
            'data' => $this->formatAnnouncement($announcement),
        ], 200);
    }

    public function destroy($id)
    {
        $announcement = Announcement::find($id);

        if (! $announcement) {
            return response()->json([
                'status' => false,
                'message' => 'Announcement not found',
                'data' => null,
            ], 404);
        }

        if ($announcement->image && !str_starts_with($announcement->image, 'http')) {
            \Illuminate\Support\Facades\Storage::disk('custom_images')->delete($announcement->image);
            if (file_exists(storage_path('images/' . $announcement->image))) {
                @unlink(storage_path('images/' . $announcement->image));
            }
        }

        $announcement->delete();

        return response()->json([
            'status' => true,
            'message' => 'Announcement deleted successfully',
            'data' => null,
        ], 200);
    }

    public function toggleStatus($id)
    {
        $announcement = Announcement::find($id);

        if (! $announcement) {
            return response()->json([
                'status' => false,
                'message' => 'Announcement not found',
                'data' => null,
            ], 404);
        }

        $announcement->status = $announcement->status === 'active' ? 'inactive' : 'active';
        $announcement->save();

        return response()->json([
            'status' => true,
            'message' => 'Announcement status updated successfully',
            'data' => $this->formatAnnouncement($announcement),
        ], 200);
    }

    public function markAsRead(Request $request, $id)
    {
        $userId = $request->input('user_id') ?: $request->user()?->user_id;
        if (!$userId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID is required',
            ], 400);
        }

        $announcement = Announcement::find($id);
        if (!$announcement) {
            return response()->json([
                'status' => false,
                'message' => 'Announcement not found',
            ], 404);
        }

        \App\Models\UserAnnouncementRead::updateOrCreate(
            ['user_id' => $userId, 'announcement_id' => $id],
            ['read_at' => now()]
        );

        return response()->json([
            'status' => true,
            'message' => 'Announcement marked as read',
        ], 200);
    }

    public function markAllAsRead(Request $request)
    {
        $userId = $request->input('user_id') ?: $request->user()?->user_id;
        if (!$userId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID is required',
            ], 400);
        }

        $activeIds = Announcement::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->pluck('announcement_id');

        foreach ($activeIds as $announcementId) {
            \App\Models\UserAnnouncementRead::updateOrCreate(
                ['user_id' => $userId, 'announcement_id' => $announcementId],
                ['read_at' => now()]
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'All announcements marked as read',
        ], 200);
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->input('user_id') ?: $request->user()?->user_id;
        if (!$userId) {
            return response()->json([
                'status' => true,
                'data' => ['unread_count' => 0],
            ], 200);
        }

        $activeIds = Announcement::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->pluck('announcement_id')
            ->toArray();

        $readIds = \App\Models\UserAnnouncementRead::where('user_id', $userId)
            ->whereIn('announcement_id', $activeIds)
            ->pluck('announcement_id')
            ->toArray();

        $unreadCount = count(array_diff($activeIds, $readIds));

        return response()->json([
            'status' => true,
            'data' => ['unread_count' => max(0, $unreadCount)],
        ], 200);
    }

    protected function formatAnnouncement(Announcement $announcement, ?array $readIds = null): array
    {
        $publishDate = $announcement->publish_date ? \Carbon\Carbon::parse($announcement->publish_date) : null;
        $endDate = $announcement->end_date ? \Carbon\Carbon::parse($announcement->end_date) : null;

        $isExpired = $endDate ? $endDate->isPast() : false;
        $isScheduled = $publishDate ? $publishDate->isFuture() : false;
        $isActive = ($announcement->status === 'active') && ! $isExpired && ! $isScheduled;

        $isRead = false;
        if ($readIds !== null) {
            $isRead = in_array($announcement->announcement_id, $readIds);
        }

        return [
            'announcement_id' => $announcement->announcement_id,
            'title' => $announcement->title,
            'announcement_type' => $announcement->announcement_type,
            'description' => $announcement->description,
            'image' => $announcement->image,
            'publish_date' => $this->serializeDate($announcement->publish_date),
            'end_date' => $this->serializeDate($announcement->end_date),
            'status' => $announcement->status,
            'is_active' => $isActive,
            'is_expired' => $isExpired,
            'is_scheduled' => $isScheduled,
            'is_read' => $isRead,
            'user_id' => $announcement->user_id,
            'user_name' => $announcement->user?->username,
        ];
    }

    protected function serializeDate($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (method_exists($value, 'toIso8601String')) {
            return $value->toIso8601String();
        }

        return (string) $value;
    }
}
