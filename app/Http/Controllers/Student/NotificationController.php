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

    public function index(Request $request): View
    {
        return view('student.notifications', $this->notificationService->forUser($request->user()));
    }
}
