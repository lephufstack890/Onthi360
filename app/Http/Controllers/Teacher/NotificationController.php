<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /** teacher.notifications.index — danh sách thông báo (App\Services\NotificationService dùng chung mọi vai trò). */
    public function index(): View
    {
        return view('teacher.notifications.index', $this->notificationService->forUser(Auth::user()));
    }
}
