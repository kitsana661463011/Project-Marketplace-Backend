<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->query('user_id');

        $query = Notification::query();

        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        if ($request->query('unread_only') === 'true' || $request->query('unread_only') === '1') {
            $query->where('is_read', false);
        }

        $notifications = $query->orderBy('notify_date', 'desc')
            ->orderBy('notification_id', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->notification_id,
                    'notification_id' => $item->notification_id,
                    'user_id' => $item->user_id,
                    'message' => $item->message,
                    'type' => $item->type ?? 'other',
                    'is_read' => (bool) $item->is_read,
                    'notify_date' => $item->notify_date ? $item->notify_date->toIso8601String() : null,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => $notifications,
        ], 200);
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->query('user_id');

        $query = Notification::where('is_read', false);

        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        $count = $query->count();

        return response()->json([
            'status' => true,
            'unread_count' => $count,
            'data' => ['unread_count' => $count],
        ], 200);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->is_read = true;
        $notification->save();

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read',
            'data' => $notification,
        ], 200);
    }

    public function markAllAsRead(Request $request)
    {
        $userId = $request->input('user_id') ?? $request->query('user_id');

        if (empty($userId)) {
            return response()->json([
                'status' => false,
                'message' => 'User ID is required',
            ], 422);
        }

        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read',
        ], 200);
    }

    public function destroy($id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'status' => true,
            'message' => 'Notification deleted successfully',
        ], 200);
    }
}
