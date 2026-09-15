<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Enums\PathLanguage;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Services\Admin\LearningPathService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Quản trị Lộ trình học (A6–A9, A12).
 *
 * CHỈ QUẢN TRỊ VIÊN: mọi đường dẫn khai báo bên trong nhóm role:admin,super_admin ở
 * routes/web.php.
 *
 * Controller cố ý mỏng: nhận dữ liệu nhập, kiểm tra định dạng, gọi Service. Mọi luật nghiệp
 * vụ nằm ở App\Services\Admin\LearningPathService và App\Support\LearningPathReadiness.
 */
class LearningPathController extends Controller
{
    public function __construct(private readonly LearningPathService $learningPaths) {}

    public function index(Request $request): View
    {
        return view('admin.learning-paths.index', $this->learningPaths->indexData());
    }

    public function create(Request $request): View
    {
        return view('admin.learning-paths.create', $this->learningPaths->createFormData());
    }

    public function store(Request $request): RedirectResponse
    {
        $path = $this->learningPaths->store(
            Auth::user(),
            $this->validated($request),
            $request->file('cover'),
            $request->file('share_image'),
        );

        // Tạo xong đi thẳng sang màn xếp bậc — lộ trình chưa có bậc thì chưa dùng được.
        return redirect()->route('admin.learning-paths.steps', $path->id)
            ->with('status', 'path-created');
    }

    public function edit(Request $request, LearningPath $learningPath): View
    {
        return view('admin.learning-paths.edit', $this->learningPaths->editFormData($learningPath->id));
    }

    public function update(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $this->learningPaths->update(
            $learningPath,
            $this->validated($request, $learningPath),
            $request->file('cover'),
            $request->file('share_image'),
        );

        return redirect()->route('admin.learning-paths.edit', $learningPath->id)
            ->with('status', 'path-updated');
    }

    /** Màn xếp bậc — kéo thả thứ tự, thêm/bớt khoá học. */
    public function steps(Request $request, LearningPath $learningPath): View
    {
        return view('admin.learning-paths.steps', $this->learningPaths->stepsData($learningPath->id));
    }

    public function attachCourse(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        try {
            $this->learningPaths->attachCourse($learningPath, (int) $data['course_id']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'step-attached');
    }

    public function detachCourse(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer'],
        ]);

        $this->learningPaths->detachCourse($learningPath, (int) $data['course_id']);

        return back()->with('status', 'step-detached');
    }

    /** Lưu thứ tự sau khi kéo thả. Nhận nguyên mảng id theo thứ tự mới. */
    public function reorder(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $this->learningPaths->reorder($learningPath, $data['order']);

        return back()->with('status', 'steps-reordered');
    }

    public function changeStatus(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                ContentStatus::Draft->value,
                ContentStatus::Published->value,
                ContentStatus::Archived->value,
            ])],
        ]);

        try {
            $this->learningPaths->changeStatus($learningPath, ContentStatus::from($data['status']));
        } catch (ValidationException $e) {
            // Lỗi chặn đăng hiện ngay tại màn đang đứng, không đá người dùng đi đâu cả.
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'path-status-changed');
    }

    public function destroy(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->learningPaths->destroy(Auth::user(), $learningPath, $data['reason']);

        return redirect()->route('admin.learning-paths.index')->with('status', 'path-deleted');
    }

    /** Quy tắc kiểm tra dữ liệu nhập, dùng chung cho thêm mới và sửa. */
    private function validated(Request $request, ?LearningPath $current = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('learning_paths', 'slug')->ignore($current?->id)],
            'brand' => ['nullable', 'string', 'max:60'],
            'eyebrow' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Hệ thống phục vụ học sinh lớp 1–12; để khoảng rộng cho chắc.
            'grade_from' => ['required', 'integer', 'min:1', 'max:12'],
            'grade_to' => ['required', 'integer', 'min:1', 'max:12'],
            // Ô ngôn ngữ đang ẩn (xem LearningPathService::SHOW_LANGUAGE) nên không bắt buộc;
            // vẫn kiểm tra giá trị hợp lệ phòng khi bật lại.
            'language' => ['nullable', Rule::in(array_keys(PathLanguage::options()))],
            'goal_label' => ['required', 'string', 'max:255'],
            'sessions_per_week' => ['required', 'integer', 'min:1', 'max:14'],
            'hours_per_session' => ['required', 'numeric', 'min:0.5', 'max:8'],
            'outcomes' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            // Anh dai dien lo trinh: dung o danh sach quan tri va the lo trinh ngoai trang cong khai.
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            // Anh chia se mang xa hoi: khong phai noi dung chinh cua trang, chi de dat khi chia se.
            'share_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_cover' => ['nullable', 'boolean'],
            'remove_share_image' => ['nullable', 'boolean'],
        ], [
            'grade_from.required' => 'Nhập lớp bắt đầu của khối (ví dụ 6).',
            'grade_to.required' => 'Nhập lớp kết thúc của khối (ví dụ 8).',
            'goal_label.required' => 'Nhập mục tiêu đích, ví dụ "HSG lớp 9 · Thi tuyển sinh 10 Chuyên Tin".',
            'cover.image' => 'Ảnh lộ trình phải là tệp ảnh (JPG, PNG hoặc WEBP).',
            'cover.max' => 'Ảnh lộ trình tối đa 4MB.',
            'share_image.image' => 'Ảnh chia sẻ phải là tệp ảnh (JPG, PNG hoặc WEBP).',
            'share_image.max' => 'Ảnh chia sẻ tối đa 8MB.',
        ]);
    }
}
