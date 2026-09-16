<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Enums\ProductType;
use App\Models\Course;
use App\Services\Admin\CourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(private CourseService $courseService) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'courses');

        return view('admin.courses.index', $this->courseService->indexData($tab));
    }

    public function create(Request $request): View
    {
        return view('admin.courses.create', $this->courseService->createFormData());
    }

    public function show(int $course): View
    {
        return view('admin.courses.show', $this->courseService->showData($course));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:draft,published'],
            // SỬA 15/9 (A10) — bốn trường "bậc" khi khoá học nằm trong một lộ trình.
            'level_code' => ['nullable', 'string', 'max:60'],
            'level_subtitle' => ['nullable', 'string', 'max:60'],
            'outcome' => ['nullable', 'string', 'max:160'],
            'session_count' => ['nullable', 'integer', 'min:1', 'max:999'],
            // C1 — sản phẩm bán khoá này. Bắt buộc phải là sản phẩm loại 'course': gắn nhầm
            // sang sách thì người mua trả tiền sách mà lại được vào lớp.
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('type', ProductType::Course->value)],
            // SỬA 15/9 (khách: "thêm field thumbnail") — ảnh đại diện khoá học. 4MB đủ cho ảnh
            // ngang 16:9; giới hạn định dạng để không ai tải lên .heic của iPhone rồi trình
            // duyệt không hiện được.
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
        ], [
            'product_id.exists' => 'Sản phẩm bán khoá học phải là một sản phẩm loại "Khóa học".',
        ]);

        $course = $this->courseService->store(Auth::user(), $data, $request->file('cover'));

        return redirect()->route('admin.courses.index')->with('status', 'course-created');
    }

    public function edit(int $course): View
    {
        return view('admin.courses.edit', $this->courseService->editFormData($course));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            // SỬA 15/9 (A10) — bốn trường "bậc" khi khoá học nằm trong một lộ trình.
            'level_code' => ['nullable', 'string', 'max:60'],
            'level_subtitle' => ['nullable', 'string', 'max:60'],
            'outcome' => ['nullable', 'string', 'max:160'],
            'session_count' => ['nullable', 'integer', 'min:1', 'max:999'],
            // C1 — sản phẩm bán khoá này. Bắt buộc phải là sản phẩm loại 'course': gắn nhầm
            // sang sách thì người mua trả tiền sách mà lại được vào lớp.
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('type', ProductType::Course->value)],
            // SỬA 15/9 (khách: "thêm field thumbnail") — ảnh đại diện khoá học. 4MB đủ cho ảnh
            // ngang 16:9; giới hạn định dạng để không ai tải lên .heic của iPhone rồi trình
            // duyệt không hiện được.
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
        ], [
            'product_id.exists' => 'Sản phẩm bán khoá học phải là một sản phẩm loại "Khóa học".',
        ]);

        $this->courseService->update(
            $course,
            $data,
            $request->file('cover'),
            $request->boolean('remove_cover'),
        );

        return redirect()->route('admin.courses.show', $course->id)->with('status', 'course-updated');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->courseService->destroy($course, $data['reason']);

        return redirect()->route('admin.courses.index')->with('status', 'course-deleted');
    }

    public function classesCreate(Course $course): View
    {
        return view('admin.classes.create', $this->courseService->classCreateFormData($course));
    }

    public function classesStore(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'schedule_note' => ['nullable', 'string', 'max:500'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:active,archived'],
            // SỬA 16/9 — 4 trường mô tả lớp in ra thẻ lớp ngoài trang công khai.
            'location' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:160'],
            'format' => ['nullable', 'string', 'max:60'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $classRoom = $this->courseService->storeClass($course, $data);

        return redirect()->route('admin.courses.show', $course->id)->with('status', 'class-created');
    }

    public function classesEdit(int $classRoom): View
    {
        return view('admin.classes.edit', $this->courseService->classEditFormData($classRoom));
    }

    public function classesUpdate(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'schedule_note' => ['nullable', 'string', 'max:500'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:active,archived'],
            // SỬA 16/9 — 4 trường mô tả lớp in ra thẻ lớp ngoài trang công khai.
            'location' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:160'],
            'format' => ['nullable', 'string', 'max:60'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $this->courseService->updateClass($classRoom, $data);

        return redirect()->route('admin.classes.edit', $classRoom->id)->with('status', 'class-updated');
    }

    public function classesDestroy(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $courseId = $classRoom->course_id;

        $this->courseService->destroyClass($classRoom, $data['reason']);

        return redirect()->route('admin.courses.show', $courseId)->with('status', 'class-deleted');
    }
}
