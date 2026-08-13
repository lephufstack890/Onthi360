<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Course;
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

    /** admin.courses.edit — form sửa khóa học (8.1). */
    public function edit(int $course): View
    {
        return view('admin.courses.edit', $this->courseService->editFormData($course));
    }

    /** admin.courses.update. */
    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
        ]);

        $this->courseService->update($course, $data);

        return redirect()->route('admin.courses.show', $course->id)->with('status', 'course-updated');
    }

    /** admin.courses.destroy — xóa mềm, PHẢI có lý do (10.4). */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->courseService->destroy($course, $data['reason']);

        return redirect()->route('admin.courses.index')->with('status', 'course-deleted');
    }

    /** admin.courses.classes.create — form tạo lớp thuộc khóa học này (8.1). */
    public function classesCreate(Course $course): View
    {
        return view('admin.classes.create', $this->courseService->classCreateFormData($course));
    }

    /** admin.courses.classes.store. */
    public function classesStore(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'schedule_note' => ['nullable', 'string', 'max:500'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:active,archived'],
        ]);

        $classRoom = $this->courseService->storeClass($course, $data);

        return redirect()->route('admin.courses.show', $course->id)->with('status', 'class-created');
    }

    /** admin.classes.edit — form sửa lớp (đổi giáo viên/lịch/trạng thái, 8.1/7.2). */
    public function classesEdit(int $classRoom): View
    {
        return view('admin.classes.edit', $this->courseService->classEditFormData($classRoom));
    }

    /** admin.classes.update. */
    public function classesUpdate(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'schedule_note' => ['nullable', 'string', 'max:500'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:active,archived'],
        ]);

        $this->courseService->updateClass($classRoom, $data);

        return redirect()->route('admin.classes.edit', $classRoom->id)->with('status', 'class-updated');
    }

    /** admin.classes.destroy — xóa mềm lớp, PHẢI có lý do (10.4). */
    public function classesDestroy(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $courseId = $classRoom->course_id;

        $this->courseService->destroyClass($classRoom, $data['reason']);

        return redirect()->route('admin.courses.show', $courseId)->with('status', 'class-deleted');
    }
}
