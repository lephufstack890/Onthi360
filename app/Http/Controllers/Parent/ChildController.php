<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ChildService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChildController extends Controller
{
    public function __construct(private ChildService $childService) {}

    /** parent.children.index — chỉ hiển thị học sinh đã liên kết, không cho tìm kiếm học sinh khác (10.3, 3.3). */
    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('parent.children.index', $this->childService->listForParent($user));
    }

    /** parent.children.show (PAR-02) — lịch/điểm danh/kết quả/tiến độ/review (10.3, 9.2). */
    public function show(Request $request, int $child): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'overview');

        return view('parent.children.show', $this->childService->showForParent($user, $child, $tab));
    }
}
