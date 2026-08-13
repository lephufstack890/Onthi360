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
     * student.notifications (STU-11 — phần thông báo) — dữ liệu thật qua
     * App\Services\NotificationService dùng chung mọi vai trò (kênh 'database').
     */
    public function index(Request $request): View
    {
        $notifications = $this->notificationService->forUser($request->user());

        return view('student.notifications', ['notifications' => $notifications]);
    }
}
