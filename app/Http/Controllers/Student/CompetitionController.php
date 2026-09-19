<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Services\Student\AssessmentService;
use App\Services\Student\CompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SỬA 19/9 — phía học sinh của cuộc thi: gửi đơn đăng ký, và (sau khi được duyệt) vào KHÔNG
 * GIAN THI. Trước đây thư mục này không có gì — học sinh bấm "Vào phòng thi" là nhảy thẳng vào
 * đề, không có màn cuộc thi nào của riêng mình.
 */
class CompetitionController extends Controller
{
    public function __construct(
        private readonly CompetitionService $competitionService,
        // SỬA 19/9 (2) — dùng LẠI nguyên service làm bài cũ cho phòng thi cuộc thi. Cố ý không
        // viết bản sao: mọi luật (mở/tiếp tục lượt, hết giờ tự nộp, số lượt làm lại, chấm) chỉ
        // tồn tại ở MỘT chỗ, nên sửa ở đó là cả hai đường vào cùng đúng.
        private readonly AssessmentService $assessmentService,
    ) {}

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

    /**
     * student.competitions.exam — PHÒNG THI RIÊNG CỦA CUỘC THI.
     *
     * Khác student.assessment.take ĐÚNG MỘT ĐIỂM: cái view. Toàn bộ vòng đời bài làm vẫn do
     * AssessmentService::buildTakeData() dựng (nó tự gọi AttemptService::startOrResume() và
     * ghi competition_id/competition_exam_id vào lượt làm bài), còn tự lưu / chạy thử / nộp
     * vẫn bắn về đúng 3 route cũ. Nhờ vậy học sinh vào bằng đường nào cũng ra CÙNG một lượt
     * làm bài, không đẻ ra lượt thứ hai.
     *
     * Thứ tự xử lý cố ý như sau, đi từ "chặn sớm nhất" tới "hiển thị":
     *   1. examContext() — chưa được duyệt / vòng không thuộc cuộc thi / vòng chưa gắn đề.
     *   2. buildTakeData() ném ValidationException — chưa tới giờ, đã hết giờ, hết lượt làm
     *      lại, chưa đủ quyền học liệu... Đây là thông báo NGHIỆP VỤ, hiện trang "blocked"
     *      quen thuộc chứ không phải trang lỗi kỹ thuật.
     *   3. Lượt vừa bị tự nộp vì hết giờ -> đi thẳng trang kết quả.
     *   4. Đề dạng PDF -> dùng lại màn take-pdf sẵn có (đã chạy ổn định), không dựng bản sao.
     */
    public function exam(Request $request, int $competition, int $exam): View|RedirectResponse
    {
        $user = $request->user();

        try {
            // Gộp chung một try: cả 2 bước đều ném ValidationException cho các lý do NGHIỆP VỤ
            // (chưa duyệt / chưa tới giờ / hết lượt...). Lỗi truy cập bất thường (404/403 của
            // abort()) KHÔNG phải ValidationException nên vẫn đi thẳng ra trang lỗi đúng của nó.
            $context = $this->competitionService->examContext($user, $competition, $exam);
            $data = $this->assessmentService->buildTakeData($user, $context['assessmentId']);
        } catch (ValidationException $e) {
            $message = $e->errors()['attempt'][0] ?? 'Không thể mở lượt làm bài của vòng thi này.';

            /*
             * SỬA 19/9 (4) — 3 nhánh dưới đây đều KHÔNG mở phòng thi, và trước đó chúng im lặng
             * hoàn toàn: học sinh bấm "Vào thi" rồi ra một màn khác, còn log thì sạch bong nên
             * không ai truy được vì sao. Ghi lại lý do để lần sau nhìn laravel.log là biết ngay.
             */
            Log::warning('Phòng thi cuộc thi: TỪ CHỐI MỞ', [
                'competition_id' => $competition,
                'competition_exam_id' => $exam,
                'user_id' => $user?->id,
                'ly_do' => $message,
            ]);

            return view('student.assessment.blocked', ['message' => $message]);
        }

        if ($data['attempt']->status !== AttemptStatus::InProgress) {
            Log::warning('Phòng thi cuộc thi: LƯỢT LÀM BÀI ĐÃ ĐÓNG -> chuyển sang trang kết quả', [
                'competition_id' => $competition,
                'competition_exam_id' => $exam,
                'user_id' => $user?->id,
                'attempt_id' => $data['attempt']->id,
                'trang_thai_attempt' => $data['attempt']->status->value,
            ]);

            return redirect()->route('student.assessment.result', $data['attempt']->id);
        }

        if ($data['isPdfMode']) {
            Log::warning('Phòng thi cuộc thi: ĐỀ DẠNG PDF -> dùng màn phiếu đáp án, không phải modal cuộc thi', [
                'competition_id' => $competition,
                'competition_exam_id' => $exam,
                'user_id' => $user?->id,
                'assessment_id' => $context['assessmentId'],
            ]);

            return view('student.assessment.take-pdf', $data);
        }

        Log::info('Phòng thi cuộc thi: MỞ MODAL', [
            'competition_id' => $competition,
            'competition_exam_id' => $exam,
            'user_id' => $user?->id,
            'attempt_id' => $data['attempt']->id,
            'so_cau' => count($data['questions']),
        ]);

        return view('student.competitions.exam', array_merge($data, $context));
    }
}
