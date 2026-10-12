<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): View
    {
        // SỬA 12/10 — lấy tối đa 60 thông báo mới nhất rồi phân trang 10 dòng/trang ở trình duyệt.
        return view('parent.notifications.index', $this->notificationService->forUser($request->user(), 60));
    }
}
