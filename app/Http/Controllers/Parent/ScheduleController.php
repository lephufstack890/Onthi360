<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ParentWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** parent.schedule.index — "Lịch & Điểm danh" dựng theo source mới; ?child= chọn đối tượng theo dõi (chỉ con đã xác minh, 10.3). */
class ScheduleController extends Controller
{
    public function __construct(private ParentWorkspaceService $workspace) {}

    public function index(Request $request): View
    {
        return view('parent.schedule.index', $this->workspace->snapshot($request->user(), $request->query('child')));
    }
}
