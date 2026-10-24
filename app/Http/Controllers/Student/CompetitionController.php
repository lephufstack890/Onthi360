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
        // SỬA 24/10 — luật vào thi của phòng thi mới (mật khẩu khảo sát, mã đề cuộc thi).
        private readonly \App\Services\Contest\ContestEntryService $entry,
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
    public function room(Request $request, int $competition): RedirectResponse
    {
        // SỬA 24/10 (phòng thi mới) — không gian thi giờ là chính trang /cuoc-thi/{id} (mọi người cùng xem, nút theo quyền),
        // đường dẫn cũ chuyển về đó, giữ ?vong= để link đã chia sẻ vẫn mở đúng kỳ thi.
        return redirect()->route('competitions.show', array_filter([
            'competition' => $competition,
            'vong' => $request->integer('vong') ?: null,
        ]));
    }

    /**
     * SỬA 24/10 — student.competitions.enter: nút trong hộp thoại "Sẵn sàng vào thi? / Nhập mật khẩu khảo sát".
     * Khảo sát: kiểm mật khẩu (giới hạn số lần sai) → vào danh sách + dấu đã nhập. Cuộc thi: phải được duyệt, chưa nộp,
     * nhận mã đề (ngẫu nhiên / tự chọn, chốt cố định). Xong thì sang trang làm bài. Luật: App\Services\Contest\ContestEntryService.
     */
    public function enter(Request $request, int $competition, int $exam): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['nullable', 'string', 'max:100'],
            'form' => ['nullable', 'integer', 'min:1', 'max:'.\App\Support\ContestForms::MAX],
            'paper' => ['nullable', 'integer'], // SỬA 24/10 — đề học sinh chọn (khảo sát nhiều đề)
        ]);

        try {
            $this->entry->enter($request->user(), $competition, $exam, $data['password'] ?? null, isset($data['form']) ? (int) $data['form'] : null, isset($data['paper']) ? (int) $data['paper'] : null);
        } catch (ValidationException $e) {
            return redirect()->route('competitions.show', ['competition' => $competition, 'vong' => $exam])
                ->withErrors($e->errors())->with('contest_entry_failed', true);
        }

        return redirect()->route('student.competitions.exam', ['competition' => $competition, 'exam' => $exam]);
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

        // SỬA 24/10 — khảo sát phải vừa nhập đúng mật khẩu; cuộc thi phải có mã đề (giao 1 lần, chốt cố định).
        try {
            $formNo = $this->entry->guardExamPage($user, $competition, $exam);
        } catch (ValidationException $e) {
            return redirect()->route('competitions.show', ['competition' => $competition, 'vong' => $exam])->withErrors($e->errors());
        }

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

        /*
         * SỬA 19/9 (5) — đề PDF có PHÒNG THI RIÊNG của cuộc thi (exam-pdf), không còn mượn màn
         * student.assessment.take-pdf vốn chạy trong layout học sinh (còn nguyên sidebar, nhìn
         * lệch hẳn so với phòng thi câu lập trình). Hai view cùng dùng chung dữ liệu của
         * buildTakeData() và chung partials/exam-pdf-workspace-script nên logic không tách đôi.
         */
        if ($data['isPdfMode']) {
            return view('student.assessment.take-pdf', array_merge($data, $context, $this->takeExtras($context)));
        }

        // SỬA 24/10 — mã đề hoán vị: chỉ đổi THỨ TỰ hiện câu (bài làm, chấm điểm vẫn theo question_id).
        if ($formNo > 1) {
            $ordered = \App\Support\ContestForms::order($data['questions'], $exam, $formNo);
            foreach ($ordered as $i => $q) {
                $ordered[$i]['no'] = $i + 1;
            }
            $data['questions'] = $ordered;
            $context['contestRoundLabel'] .= ' · '.\App\Support\ContestForms::label($formNo);
        }

        Log::info('Phòng thi cuộc thi: MỞ MODAL', [
            'competition_id' => $competition,
            'competition_exam_id' => $exam,
            'user_id' => $user?->id,
            'attempt_id' => $data['attempt']->id,
            'so_cau' => count($data['questions']),
        ]);

        // SỬA 24/10 (khách: "giao diện làm bài cuộc thi in đúc giao diện làm đề bên Luyện tập") — dùng CHUNG màn làm đề của
        // Luyện tập (student.assessment.take / take-pdf), chỉ đổi tiêu đề + nút Thoát quay về phòng thi. Màn riêng cũ
        // (student.competitions.exam*) để lại, không dùng nữa.
        return view('student.assessment.take', array_merge($data, $context, $this->takeExtras($context)));
    }

    /** @return array{exitUrl: string, examTitleOverride: string} */
    private function takeExtras(array $context): array
    {
        return [
            'exitUrl' => $context['contestRoomUrl'],
            // SỬA 24/10 — khảo sát: thoát giữa chừng là lượt đang làm bị huỷ, hỏi lại trước khi thoát.
            'exitWarning' => ! empty($context['contestIsSurvey'])
                ? 'Thoát bây giờ thì lượt đang làm sẽ bị huỷ — lần sau bắt đầu lại từ đầu và được chọn đề khác. Vẫn thoát?' : null,
            'examTitleOverride' => implode(' · ', array_filter([$context['contestTitle'], $context['contestRoundLabel'], $context['contestPaperLabel'] ?? null])),
        ];
    }
}
