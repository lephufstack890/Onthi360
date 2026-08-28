<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Student\LibraryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Tài liệu của tôi" (28/8 (2) — "bên giáo viên cũng xem tài liệu giống như học sinh, chỉ
 * khác được xem thêm file hướng dẫn") — teacher.library.index. TÁI DÙNG nguyên
 * App\Services\Student\LibraryService (đã viết cho học sinh trước), chỉ khác đúng 1 điểm:
 * gọi với includeGuide=true nên danh sách tài nguyên có thêm "PDF hướng dẫn" — cùng cách
 * Teacher\MaterialController tái dùng MaterialReadService của học sinh.
 */
class LibraryController extends Controller
{
    public function __construct(private LibraryService $library) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'sach');

        return view('teacher.materials.mine', $this->library->indexData($request->user(), $tab, 'teacher.library.index', true));
    }
}
