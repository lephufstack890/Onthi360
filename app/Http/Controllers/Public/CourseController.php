<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\CourseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** courses.* (PUB-03/04, 4.1 "Khóa học = khám phá chương trình" + 8.1 "khóa ≠ lớp"). */
class CourseController extends Controller
{
    public function __construct(private CourseService $courseService) {}

    /** courses.index — danh mục khóa học công khai, lọc theo môn (?subject=). */
    public function index(Request $request): View
    {
        return view('public.courses.index', $this->courseService->indexData($request->query('subject')));
    }

    /** courses.show — chi tiết khóa + các lớp đang triển khai (8.1/8.3). */
    public function show(Request $request, int $course): View
    {
        return view('public.courses.show', $this->courseService->showData($course));
    }
}
