<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CourseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(private CourseService $courseService) {}

    /** admin.courses.index — "Khóa & Lớp" (8.1: Khóa học khác Lớp học). */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'courses');

        return view('admin.courses.index', $this->courseService->indexData($tab));
    }
}
