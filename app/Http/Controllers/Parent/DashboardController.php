<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\Parent\ParentWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private ParentWorkspaceService $workspace,
        private NotificationService $notifications,
    ) {}

    /** parent.dashboard — dựng theo ParentOverview() của source mới; ?child= chọn đối tượng theo dõi. */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('parent.dashboard', $this->workspace->snapshot($user, $request->query('child')) + [
            'notes' => array_slice($this->notifications->forUser($user, 3)['items'], 0, 3),
        ]);
    }
}
