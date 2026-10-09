<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MaterialAssignment;
use App\Services\Public\MaterialAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * SỬA 9/10 — nhận lượt "Giao tài liệu" từ popup ở trang Tài liệu (bản mẫu:
 * education-main/QuickAssignButton kiểu "material"). Trả JSON để popup hiện kết quả ngay.
 *
 * Quyền kiểm ở HAI lớp: route chỉ cho người đã đăng nhập, rồi service kiểm tiếp vai trò
 * giáo viên/admin — không tin nút ẩn/hiện trên giao diện.
 */
class MaterialAssignmentController extends Controller
{
    public function __construct(private MaterialAssignmentService $assignments) {}

    /** Gợi ý học sinh cho ô chọn nhiều trong popup giao tài liệu. */
    public function students(Request $request): JsonResponse
    {
        abort_unless($this->assignments->canAssign(Auth::user()) && $this->assignments->isReady(), 403, 'Bạn không có quyền giao nội dung này.');

        $term = mb_substr((string) $request->query('q', ''), 0, 60);

        return response()->json(['students' => $this->assignments->searchStudents($term)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        abort_unless($this->assignments->canAssign($user), 403, 'Bạn không có quyền giao nội dung này.');

        if (! $this->assignments->isReady()) {
            return response()->json([
                'message' => 'Tính năng giao tài liệu chưa sẵn sàng. Quản trị viên cần chạy "php artisan migrate" trên máy chủ.',
                'errors' => ['students' => ['Tính năng giao tài liệu chưa sẵn sàng (cần chạy migrate).']],
            ], 422);
        }

        $max = MaterialAssignmentService::ASSIGN_MAX_STUDENTS;

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'student_ids' => ['required', 'array', 'min:1', 'max:'.$max],
            'student_ids.*' => ['integer', 'min:1'],
            'deadline' => ['required', 'date'],
            'access_days' => ['required', 'integer', Rule::in(MaterialAssignmentService::ACCESS_DAYS)],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'student_ids.required' => 'Chọn ít nhất 1 học sinh.',
            'student_ids.min' => 'Chọn ít nhất 1 học sinh.',
            'student_ids.max' => "Mỗi lần giao tối đa {$max} học sinh.",
            'student_ids.*.integer' => 'Có học sinh không hợp lệ. Hãy chọn lại danh sách.',
            'deadline.required' => 'Vui lòng chọn ngày và giờ hạn đọc hợp lệ.',
            'deadline.date' => 'Vui lòng chọn ngày và giờ hạn đọc hợp lệ.',
            'access_days.required' => 'Vui lòng chọn thời hạn cấp quyền đọc hợp lệ.',
            'access_days.in' => 'Vui lòng chọn thời hạn cấp quyền đọc hợp lệ.',
            'note.max' => 'Lời nhắn tối đa 1000 ký tự.',
        ]);

        // Ô datetime-local gửi giờ theo múi giờ người dùng (không kèm offset): hiểu theo múi giờ ứng dụng.
        $deadline = Carbon::parse($data['deadline'], config('app.timezone'));

        $assignments = $this->assignments->assignMany(
            $user,
            (int) $data['product_id'],
            $data['student_ids'],
            $deadline,
            (int) $data['access_days'],
            $data['note'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'count' => $assignments->count(),
            'students' => $assignments->map(fn (MaterialAssignment $a) => [
                'name' => (string) $a->student?->name,
                'account' => $a->student?->email ?: $a->student?->phone,
            ])->values(),
            'deadline' => $deadline->format('d/m/Y H:i'),
            'accessDays' => (int) $data['access_days'],
        ]);
    }
}
