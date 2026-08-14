<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /** parent.notifications.index — App\Services\NotificationService dùng chung mọi vai trò (kênh 'database'). */
    public function index(Request $request): View
    {
        return view('parent.notifications.index', $this->notificationService->forUser($request->user()));
    }
}
