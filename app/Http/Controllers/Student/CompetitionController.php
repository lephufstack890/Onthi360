<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\CompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SỬA 19/9 — phía học sinh của cuộc thi: gửi đơn đăng ký, và (sau khi được duyệt) vào KHÔNG
 * GIAN THI. Trước đây thư mục này không có gì — học sinh bấm "Vào phòng thi" là nhảy thẳng vào
 * đề, không có màn cuộc thi nào của riêng mình.
 */
class CompetitionController extends Controller
{
    public function __construct(private readonly CompetitionService $competitionService) {}

    /**
     * student.competitions.requestJoin — bấm "Đăng ký tham gia" ở popup chi tiết cuộc thi.
     *
     * Quay lại ĐÚNG trang vừa bấm (popup nằm ở trang danh sách công khai, cũng có thể ở trang
     * chi tiết) kèm cờ để hiện thông báo — cùng cách nút "Đăng ký học" của lớp đang làm.
     */
    public function requestJoin(Request $request, int $competition): RedirectResponse
    {
        $this->competitionService->requestJoin($request->user(), $competition);

        return back()->with('status', 'competition-join-requested');
    }

    /**
     * student.competitions.room — KHÔNG GIAN THI. Chỉ mở cho học sinh đã được ban tổ chức duyệt;
     * kiểm tra nằm trong roomData() (không tin giao diện đã ẩn link).
     */
    public function room(Request $request, int $competition): View
    {
        // ?vong=<id kỳ thi> — cho phép chia sẻ đường dẫn tới đúng vòng đang xem; giá trị lạ
        // (vòng của cuộc thi khác, id không tồn tại) bị roomData() bỏ qua và tự chọn vòng hợp lý.
        $selectedExamId = $request->integer('vong') ?: null;

        return view('student.competitions.room', $this->competitionService->roomData($request->user(), $competition, $selectedExamId));
    }
}
