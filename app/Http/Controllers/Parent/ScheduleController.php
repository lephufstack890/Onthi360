<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ChildService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private ChildService $childService) {}

    /**
     * parent.schedule.index (10.3: "lịch, điểm danh") — mục điều hướng cấp cao, không gắn
     * với 1 con cụ thể. 1 con đã xác minh -> vào thẳng tab "Lịch & Điểm danh" của con đó
     * (parent.children.show); nhiều con -> hiện danh sách chọn con (không dựng lại UI lịch/
     * điểm danh ở đây, tái dùng đúng 1 nguồn hiển thị đã có ở PAR-02).
     */
    public function index(Request $request): View|RedirectResponse
    {
        $children = $this->childService->verifiedChildrenForParent($request->user());

        if (count($children) === 1) {
            return redirect()->route('parent.children.show', ['child' => $children[0]['id'], 'tab' => 'schedule']);
        }

        return view('parent.schedule.index', ['children' => $children]);
    }
}
