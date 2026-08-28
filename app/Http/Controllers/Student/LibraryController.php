<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\LibraryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Tài liệu của tôi" (28/8) — student.library.index. Chỉ resolve request/tab rồi render;
 * mọi luật (sản phẩm nào đã mua, mục lục, tài nguyên nào học sinh được xem) nằm ở
 * App\Services\Student\LibraryService.
 */
class LibraryController extends Controller
{
    public function __construct(private LibraryService $library) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'sach');

        return view('student.materials.mine', $this->library->indexData($request->user(), $tab));
    }
}
