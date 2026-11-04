<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function read(string $notification)
    {
        $user = Auth::user();
        $record = $user->notifications()->where('id', $notification)->first();
        $url = $record?->data['url'] ?? null;

        $record?->markAsRead();

        // SỬA 4/11 — thông báo không có đường dẫn (thông báo lớp, thông báo Quản trị…) mở trang đọc đủ nội dung thay vì về Tổng quan.
        if (! $url && $record !== null) {
            return redirect()->route('notifications.show', $record->id);
        }

        return redirect($url ?: route('dashboard'));
    }

    public function readAll(Request $request)
    {
        $this->notificationService->markAllAsRead(Auth::user());

        // SỬA 4/11 — nút "Đọc tất cả" ở chuông gọi ngầm (không tải lại trang).
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'unread' => 0]);
        }

        return redirect()->back();
    }

    /** SỬA 4/11 — nút "Đã đọc" từng thông báo ở chuông / tab Thông báo trang chủ (gọi ngầm, không mở thông báo). */
    public function markRead(Request $request, string $notification)
    {
        $user = Auth::user();
        $this->notificationService->markAsRead($user, $notification);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'unread' => $user->unreadNotifications()->count()]);
        }

        return redirect()->back();
    }
}
