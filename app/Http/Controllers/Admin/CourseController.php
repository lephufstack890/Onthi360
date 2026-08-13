<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    /** admin.courses.create — form tạo khóa học mới (8.1). */
    public function create(Request $request): View
    {
        return view('admin.courses.create', $this->courseService->createFormData());
    }

    /** admin.courses.show — chi tiết khóa học + danh sách lớp thuộc khóa (8.1). */
    public function show(int $course): View
    {
        return view('admin.courses.show', $this->courseService->showData($course));
    }

    /** admin.courses.store — tạo khóa học mới, gắn admin đang đăng nhập làm người tạo. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:draft,published'],
        ]);

        $course = $this->courseService->store(Auth::user(), $data);

        return redirect()->route('admin.courses.index')->with('status', 'course-created');
    }
}
