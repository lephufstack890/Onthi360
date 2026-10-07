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
            // SỬA 1/10 — "Giới thiệu khoá học": bài giới thiệu đầy đủ do CKEditor soạn, khác
            // 'description' (tóm tắt 1-2 câu). Trần rộng hơn nhiều vì là HTML có thẻ, danh sách,
            // đôi khi cả ảnh — xem migration add_intro_to_courses_table.
            'intro' => ['nullable', 'string', 'max:200000'],
            'subject' => ['nullable', 'string', 'max:60'],
            // SỬA 30/9 (khách: "chỗ chọn khối và lớp thì cho chọn nhiều") — ô "Khối lớp" giờ là
            // các ô tick, gửi lên mảng 'grades'. Vẫn nhận 'grade' (1 chuỗi) cho các chỗ gọi cũ.
            'grades' => ['nullable', 'array', 'max:12'],
            'grades.*' => ['string', 'max:20'],
            'grade' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'string', 'in:draft,published'],
            // SỬA 15/9 (A10) — bốn trường "bậc" khi khoá học nằm trong một lộ trình.
            'level_code' => ['nullable', 'string', 'max:60'],
            'level_subtitle' => ['nullable', 'string', 'max:60'],
            'outcome' => ['nullable', 'string', 'max:160'],
            'session_count' => ['nullable', 'integer', 'min:1', 'max:999'],
            // SỬA 30/9 (khách: "số buổi nó kiểu 33-50 buổi") — cận trên của khoảng số buổi.
            // gte:session_count để không ai nhập ngược thành "50 đến 33".
            'session_count_max' => ['nullable', 'integer', 'min:1', 'max:999', 'gte:session_count'],
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
            'session_count_max.gte' => 'Số buổi ở ô sau phải lớn hơn hoặc bằng ô trước (ví dụ 33 đến 50).',
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
            // SỬA 1/10 — "Giới thiệu khoá học": bài giới thiệu đầy đủ do CKEditor soạn, khác
            // 'description' (tóm tắt 1-2 câu). Trần rộng hơn nhiều vì là HTML có thẻ, danh sách,
            // đôi khi cả ảnh — xem migration add_intro_to_courses_table.
            'intro' => ['nullable', 'string', 'max:200000'],
            'subject' => ['nullable', 'string', 'max:60'],
            // SỬA 30/9 (khách: "chỗ chọn khối và lớp thì cho chọn nhiều") — ô "Khối lớp" giờ là
            // các ô tick, gửi lên mảng 'grades'. Vẫn nhận 'grade' (1 chuỗi) cho các chỗ gọi cũ.
            'grades' => ['nullable', 'array', 'max:12'],
            'grades.*' => ['string', 'max:20'],
            'grade' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            // SỬA 15/9 (A10) — bốn trường "bậc" khi khoá học nằm trong một lộ trình.
            'level_code' => ['nullable', 'string', 'max:60'],
            'level_subtitle' => ['nullable', 'string', 'max:60'],
            'outcome' => ['nullable', 'string', 'max:160'],
            'session_count' => ['nullable', 'integer', 'min:1', 'max:999'],
            // SỬA 30/9 (khách: "số buổi nó kiểu 33-50 buổi") — cận trên của khoảng số buổi.
            // gte:session_count để không ai nhập ngược thành "50 đến 33".
            'session_count_max' => ['nullable', 'integer', 'min:1', 'max:999', 'gte:session_count'],
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
            'session_count_max.gte' => 'Số buổi ở ô sau phải lớn hơn hoặc bằng ô trước (ví dụ 33 đến 50).',
        ]);

        $this->courseService->update(
            $course,
            $data,
            $request->file('cover'),
            $request->boolean('remove_cover'),
        );

        return redirect()->route('admin.courses.show', $course->id)->with('status', 'course-updated');
    }

    /**
     * SỬA 7/10 (khách: "xoá khoá học là toàn bộ file, lớp, bất cứ gì liên quan đều xoá hết") — xoá
     * VĨNH VIỄN kèm mọi dữ liệu liên quan, xem CourseService::destroy(). Lý do giờ là tuỳ chọn:
     * nút Xoá ở danh sách Khóa & Lớp không có ô nhập lý do.
     */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $title = $course->title;

        $stats = $this->courseService->destroy($course, $data['reason'] ?? null);

        return redirect()->route('admin.courses.index')
            ->with('status', 'course-deleted')
            ->with('statusMessage', 'Đã xóa vĩnh viễn khóa học "'.$title.'" cùng '.$stats['classes'].' lớp, '
                .$stats['students'].' học viên, '.$stats['sessions'].' buổi học, '.$stats['attempts'].' lượt làm bài của học sinh và các dữ liệu liên quan.');
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

    /**
     * SỬA 7/10 — xoá VĨNH VIỄN lớp kèm mọi dữ liệu của lớp, xem CourseService::destroyClass().
     * 'return' = 'index' khi bấm từ danh sách Khóa & Lớp (quay về đúng tab Lớp học); mặc định quay
     * về trang chi tiết khoá như cũ.
     */
    public function classesDestroy(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'return' => ['nullable', 'string', 'in:index'],
        ]);
        $courseId = $classRoom->course_id;
        $name = $classRoom->name;

        $stats = $this->courseService->destroyClass($classRoom, $data['reason'] ?? null);

        $redirect = ($data['return'] ?? null) === 'index'
            ? redirect()->route('admin.courses.index', ['tab' => 'classes'])
            : redirect()->route('admin.courses.show', $courseId);

        return $redirect
            ->with('status', 'class-deleted')
            ->with('statusMessage', 'Đã xóa vĩnh viễn lớp "'.$name.'" cùng '.$stats['students'].' học viên, '
                .$stats['sessions'].' buổi học, '.$stats['attempts'].' lượt làm bài của học sinh và các dữ liệu liên quan.');
    }
}
