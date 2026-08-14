<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ChildService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function __construct(private ChildService $childService) {}

    /**
     * parent.results.index (10.3: "tiến độ, kết quả mới công bố") — mục điều hướng cấp cao,
     * không gắn với 1 con cụ thể. 1 con đã xác minh -> vào thẳng tab "Kết quả & Tiến độ" của
     * con đó (parent.children.show); nhiều con -> hiện danh sách chọn con.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $children = $this->childService->verifiedChildrenForParent($request->user());

        if (count($children) === 1) {
            return redirect()->route('parent.children.show', ['child' => $children[0]['id'], 'tab' => 'results']);
        }

        return view('parent.results.index', ['children' => $children]);
    }
}
