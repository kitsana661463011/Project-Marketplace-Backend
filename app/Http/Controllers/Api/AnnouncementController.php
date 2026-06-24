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

        $announcements = $query->orderByDesc('publish_date')->get()->map(function (Announcement $announcement) {
            return [
                'announcement_id' => $announcement->announcement_id,
                'title' => $announcement->title,
                'announcement_type' => $announcement->announcement_type,
                'description' => $announcement->description,
                'publish_date' => $this->serializeDate($announcement->publish_date),
                'status' => $announcement->status,
                'user_id' => $announcement->user_id,
                'user_name' => $announcement->user?->username,
            ];
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
            'status' => ['nullable', 'in:active,inactive'],
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $announcement = Announcement::create([
            'title' => $request->input('title'),
            'announcement_type' => $request->input('announcement_type'),
            'description' => $request->input('description'),
            'publish_date' => now(),
            'status' => $request->input('status', 'active'),
            'user_id' => $request->input('user_id'),
        ]);

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

    protected function formatAnnouncement(Announcement $announcement): array
    {
        return [
            'announcement_id' => $announcement->announcement_id,
            'title' => $announcement->title,
            'announcement_type' => $announcement->announcement_type,
            'description' => $announcement->description,
            'publish_date' => $this->serializeDate($announcement->publish_date),
            'status' => $announcement->status,
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
