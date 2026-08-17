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

        return redirect($url ?: route('dashboard'));
    }

    public function readAll(Request $request)
    {
        $this->notificationService->markAllAsRead(Auth::user());

        return redirect()->back();
    }
}
