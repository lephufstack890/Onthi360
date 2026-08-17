<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\TeacherService;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function __construct(private readonly TeacherService $teacherService) {}

    /** teachers.index (PUB-10, 12.2) — trang vinh danh giáo viên tiêu biểu, dữ liệu thật. */
    public function index(): View
    {
        return view('public.teachers.index', $this->teacherService->indexData());
    }
}
