<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\Teacher\TeacherWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly TeacherWorkspaceService $workspace,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $classes = $this->workspace->classOptions($user);
        $notices = $this->workspace->announcements($user);
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? collect($notices)->first(fn ($n) => $n['id'] === $editId && $n['state'] === 'draft') : null;

        return view('teacher.notifications.index', $this->notificationService->forUser($user) + [
            'tab' => $request->query('tab') === 'inbox' ? 'inbox' : 'compose',
            'classes' => $classes,
            'notices' => $notices,
            'recipientCounts' => $this->workspace->recipientCounts(array_column($classes, 'id')),
            'editing' => $editing,
            'preClass' => (int) $request->query('class', 0),
            'initialFilter' => in_array($request->query('filter'), ['draft', 'published'], true) ? $request->query('filter') : 'all',
        ]);
    }
}
