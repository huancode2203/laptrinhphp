<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['unread' => ['sometimes', 'boolean']]);
        $query = $request->user()->notifications()->orderByDesc('created_at')->orderBy('id');
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }
        $notifications = $query->paginate(20)->through(fn ($notification) => [
            'id' => $notification->id, 'data' => $notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
        ]);

        return response()->json(['data' => $notifications, 'unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return response()->json(['message' => 'Đã đọc thông báo.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Đã đọc tất cả thông báo.']);
    }
}
