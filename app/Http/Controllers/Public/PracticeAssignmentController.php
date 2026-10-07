<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PracticeAssignment;
use App\Services\Public\PracticeAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * SỬA 7/10 — nhận lượt "Giao bài" / "Giao đề" từ popup ở trang Luyện tập (bản mẫu:
 * education-main/QuickAssignButton). Trả JSON để popup hiện kết quả ngay, không tải lại trang.
 *
 * Quyền kiểm ở HAI lớp: route chỉ cho người đã đăng nhập, rồi service kiểm tiếp vai trò
 * giáo viên/admin — không tin nút ẩn/hiện trên giao diện.
 */
class PracticeAssignmentController extends Controller
{
    public function __construct(private PracticeAssignmentService $assignments) {}

    /** Gợi ý học sinh cho ô chọn nhiều (Select2) trong popup giao bài. */
    public function students(Request $request): JsonResponse
    {
        abort_unless($this->assignments->canAssign(Auth::user()), 403, 'Bạn không có quyền giao nội dung này.');

        $term = mb_substr((string) $request->query('q', ''), 0, 60);

        return response()->json(['students' => $this->assignments->searchStudents($term)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        abort_unless($this->assignments->canAssign($user), 403, 'Bạn không có quyền giao nội dung này.');

        $max = PracticeAssignmentService::ASSIGN_MAX_STUDENTS;

        $data = $request->validate([
            'type' => ['required', Rule::in([PracticeAssignment::TYPE_PROBLEM, PracticeAssignment::TYPE_EXAM])],
            'subject_id' => ['required', 'integer', 'min:1'],
            'student_ids' => ['required', 'array', 'min:1', 'max:'.$max],
            'student_ids.*' => ['integer', 'min:1'],
            'deadline' => ['required', 'date'],
        ], [
            'student_ids.required' => 'Chọn ít nhất 1 học sinh.',
            'student_ids.min' => 'Chọn ít nhất 1 học sinh.',
            'student_ids.max' => "Mỗi lần giao tối đa {$max} học sinh.",
            'student_ids.*.integer' => 'Có học sinh không hợp lệ. Hãy chọn lại danh sách.',
            'deadline.required' => 'Vui lòng chọn ngày và giờ hạn nộp hợp lệ.',
            'deadline.date' => 'Vui lòng chọn ngày và giờ hạn nộp hợp lệ.',
        ]);

        // Ô datetime-local gửi giờ theo múi giờ của người dùng (không kèm offset): hiểu theo
        // múi giờ của ứng dụng (config app.timezone), cùng múi mà phần còn lại của hệ thống dùng.
        $deadline = Carbon::parse($data['deadline'], config('app.timezone'));

        $assignments = $this->assignments->assignMany(
            $user,
            $data['type'],
            (int) $data['subject_id'],
            $data['student_ids'],
            $deadline,
        );

        return response()->json([
            'ok' => true,
            'count' => $assignments->count(),
            'students' => $assignments->map(fn (PracticeAssignment $a) => [
                'name' => (string) $a->student?->name,
                'account' => $a->student?->email ?: $a->student?->phone,
            ])->values(),
            'deadline' => $deadline->format('d/m/Y H:i'),
        ]);
    }
}
