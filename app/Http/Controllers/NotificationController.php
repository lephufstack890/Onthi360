<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Điểm click-through DÙNG CHUNG cho chuông thông báo toàn cục (mọi vai trò) — xem
 * resources/views/partials/notifications-bell.blade.php. Đặt ở namespace gốc (không
 * dưới Teacher/Student) vì bell được include ở layouts.app cho MỌI role.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /** notifications.read — đánh dấu đã đọc rồi chuyển tới url gắn kèm thông báo (nếu có). */
    public function read(string $notification)
    {
        $user = Auth::user();
        $record = $user->notifications()->where('id', $notification)->first();
        $url = $record?->data['url'] ?? null;

        $record?->markAsRead();

        return redirect($url ?: route('dashboard'));
    }

    /** notifications.readAll — đánh dấu tất cả đã đọc, quay lại trang trước đó. */
    public function readAll(Request $request)
    {
        $this->notificationService->markAllAsRead(Auth::user());

        return redirect()->back();
    }
}
