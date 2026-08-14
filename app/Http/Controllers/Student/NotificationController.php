<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * student.notifications (STU-11 — phần thông báo) — dữ liệu thật qua
     * App\Services\NotificationService dùng chung mọi vai trò (kênh 'database'), cùng cách
     * dùng như Teacher\NotificationController — trước đây đi qua wrapper
     * App\Services\Student\NotificationService chỉ trả 'items', làm mất 'unreadCount' nên
     * trang này không hiện được số chưa đọc/nút "Đánh dấu tất cả đã đọc" như trang giáo viên.
     */
    public function index(Request $request): View
    {
        return view('student.notifications', $this->notificationService->forUser($request->user()));
    }
}
