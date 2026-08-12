<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\NotificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * student.notifications (STU-11 — phần thông báo).
     * TODO: chưa có bảng notifications trong schema hiện tại — cần thêm migration +
     * model Notification (hoặc dùng Laravel Notifications mặc định) trước khi hiển thị
     * dữ liệu thật. Hiện trả về danh sách rỗng để UI hiển thị đúng trạng thái "chưa có
     * thông báo" thay vì dữ liệu giả.
     */
    public function index(Request $request): View
    {
        $notifications = $this->notificationService->forUser($request->user());

        return view('student.notifications', ['notifications' => $notifications]);
    }
}
