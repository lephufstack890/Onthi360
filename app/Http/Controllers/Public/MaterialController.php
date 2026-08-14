<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\MaterialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** materials.* (PUB-05/06, 4.1 "tabs Sách/Chuyên đề/Đề thi" + 7.5 "màn mua theo vai trò"). */
class MaterialController extends Controller
{
    public function __construct(private MaterialService $materialService) {}

    /** materials.index — tabs Sách/Chuyên đề/Đề thi (?tab=sach|chuyen-de|de-thi). */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'sach');

        return view('public.materials.index', $this->materialService->indexData($tab));
    }

    /** materials.show — chi tiết tài liệu + mục lục + CTA mua quyền (7.5). */
    public function show(Request $request, int $material): View
    {
        return view('public.materials.show', $this->materialService->showData($material));
    }
}
