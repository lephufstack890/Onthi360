<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Services\Student\AssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentController extends Controller
{
    public function __construct(
        private AssessmentService $assessmentService,
    ) {}

    public function take(Request $request, int $assessment): View|RedirectResponse
    {
        $user = $request->user();
        $assignmentId = $request->filled('assignment') ? (int) $request->query('assignment') : null;

        try {
            $data = $this->assessmentService->buildTakeData($user, $assessment, $assignmentId);
        } catch (ValidationException $e) {
            // SỬA 18/8: trước đây abort(422, ...) — bay thẳng ra trang lỗi kỹ thuật của
            // Laravel (raw exception page, có stack trace nếu APP_DEBUG=true), dù lý do chặn
            // (hết lượt làm lại theo resubmission_policy, bài giao chưa mở, chưa đủ quyền học
            // liệu...) là thông báo NGHIỆP VỤ bình thường học sinh cần đọc được, không phải
            // lỗi hệ thống. Hiện view riêng (không đụng route/layout khác) — dùng đúng layout
            // học sinh quen thuộc, kèm 2 lối ra rõ ràng.
            return view('student.assessment.blocked', [
                'message' => $e->errors()['attempt'][0] ?? 'Không thể mở lượt làm bài.',
            ]);
        }

        // buildTakeData() tự nộp attempt nếu vừa phát hiện đã hết giờ (AttemptService::
        // finalizeIfExpired() — vd học sinh đóng tab lúc hết giờ rồi quay lại sau) — khi đó
        // không còn gì để "làm bài" nữa, đưa thẳng sang trang kết quả thay vì hiện lại trang
        // làm bài (input đã khoá) rồi bắt học sinh tự bấm "Nộp bài" một lần vô nghĩa nữa.
        if ($data['attempt']->status !== AttemptStatus::InProgress) {
            return redirect()->route('student.assessment.result', $data['attempt']->id);
        }

        // SỬA 19/8 (Giai đoạn 2 — đề PDF): cùng route/action, chỉ chọn view khác theo
        // content_mode của đề — xem App\Services\Student\AssessmentService::buildTakeData().
        $view = $data['isPdfMode'] ? 'student.assessment.take-pdf' : 'student.assessment.take';

        return view($view, $data);
    }

    /**
     * student.assessment.take.save — "Lưu nháp". Hỗ trợ 2 kiểu gọi: form POST thường (điều
     * hướng lại trang làm bài, KHÔNG cần JS — vẫn giữ để không phá hành vi cũ) và fetch() với
     * header Accept: application/json (autosave thời gian thực từ resources/views/student/
     * assessment/take.blade.php — mỗi lần học sinh trả lời xong 1 câu, KHÔNG đợi bấm nút).
     * Dùng CHUNG cho cả đề câu hỏi rời VÀ đề PDF — $answers hình dạng khác nhau, nhưng
     * AssessmentService::saveDraftAnswers() tự rẽ nhánh theo content_mode.
     */
    /**
     * student.assessment.take.run (SỬA 18/9 (2)) — chạy thử mã của 1 câu trong đề với dữ liệu
     * vào tự gõ, trả JSON cho ô Output. KHÔNG chấm điểm, không đụng bài làm đã lưu — xem
     * Student\AssessmentService::runCodeOnce().
     *
     * Luôn trả 200 kèm ok=false + message khi hỏng: phía trình duyệt chỉ đọc một chỗ là biết
     * hiện gì, không phải phân biệt lỗi HTTP với lỗi nghiệp vụ.
     */
    public function runCode(Request $request, int $attempt): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'code_source' => ['nullable', 'string', 'max:20000'],
            'language' => ['nullable', 'string', 'max:30'],
            'stdin' => ['nullable', 'string', 'max:20000'],
        ]);

        return response()->json($this->assessmentService->runCodeOnce($request->user(), $attempt, $data));
    }

    public function saveAnswers(Request $request, int $attempt): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $answers = $request->input('answers', []);

        try {
            $attemptModel = $this->assessmentService->saveDraftAnswers($user, $attempt, $answers);
        } catch (ValidationException $e) {
            // Cả 2 lý do có thể ném ra ở đây (AttemptService::saveAnswer(): đã nộp trước đó,
            // HOẶC vừa hết giờ nên vừa được tự động nộp NGAY trong lời gọi này) đều đồng nghĩa
            // 1 điều với client: lượt làm bài không còn "đang làm" nữa — sang thẳng kết quả.
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'expired' => true,
                    'resultUrl' => route('student.assessment.result', $attempt),
                ]);
            }

            return redirect()->back()->withErrors($e->errors());
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        $params = ['assessment' => $attemptModel->assessment_id];
        if ($attemptModel->assignment_id !== null) {
            $params['assignment'] = $attemptModel->assignment_id;
        }

        return redirect()->route('student.assessment.take', $params)->with('status', 'draft-saved');
    }

    /** student.assessment.take.submit — nộp bài, khoá lượt làm bài rồi sang trang kết quả. */
    /**
     * SỬA 24/9 (khách: "hiển thị modal vậy được rồi, không cần chuyển qua trang result đâu") —
     * nộp đề giờ trả JSON khi được gọi bằng AJAX, để màn làm bài hiện điểm NGAY trong hộp
     * "đang chấm" thay vì đá học sinh sang một trang khác.
     *
     * Vẫn GIỮ NGUYÊN đường cũ (redirect sang trang kết quả) cho request thường: form nộp thật
     * vẫn nằm trong trang, JavaScript hỏng hoặc bị chặn thì nộp bài vẫn phải chạy được — đây là
     * thao tác không được phép lệ thuộc vào JS.
     */
    public function submit(Request $request, int $attempt): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $answers = $request->input('answers', []);
        $wantsJson = $request->ajax() || $request->wantsJson();

        try {
            $attemptModel = $this->assessmentService->submitAttempt($user, $attempt, $answers);
        } catch (ValidationException $e) {
            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => collect($e->errors())->flatten()->first() ?? 'Không nộp được bài.',
                ], 422);
            }

            return redirect()->back(fallback: route('dashboard'))->withErrors($e->errors());
        }

        if ($wantsJson) {
            return response()->json(['ok' => true] + $this->attemptSummary($attemptModel));
        }

        return redirect()->route('student.assessment.result', $attemptModel->id);
    }

    /**
     * SỬA 24/9 — student.assessment.status: điểm hiện tại của một lượt làm bài, dạng JSON.
     *
     * Câu lập trình chấm chạy nền (App\Jobs\GradeCodingAnswerJob) nên lúc vừa nộp xong điểm
     * mới là TẠM TÍNH. Hộp kết quả trên màn làm bài hỏi lại route này vài giây một lần để con
     * số tự nhích lên, thay vì bắt học sinh tải lại trang.
     */
    public function status(Request $request, int $attempt): JsonResponse
    {
        $attemptModel = $this->assessmentService->attemptForUser($request->user(), $attempt);

        return response()->json(['ok' => true] + $this->attemptSummary($attemptModel));
    }

    /** @return array<string, mixed> */
    private function attemptSummary(\App\Models\Attempt $attempt): array
    {
        $total = $attempt->assessment?->total_points;

        return [
            'attemptId' => $attempt->id,
            // Điểm cắt đuôi số 0 cho gọn: "30" thay vì "30.00".
            'score' => $attempt->total_score === null
                ? '—'
                : rtrim(rtrim(number_format((float) $attempt->total_score, 2, '.', ''), '0'), '.'),
            'totalPoints' => $total === null ? '—' : (string) $total,
            'isProvisional' => (bool) $attempt->is_provisional,
            'resultUrl' => route('student.assessment.result', $attempt->id),
        ];
    }

    /** student.assessment.oj (STU-06/07) — làm câu lập trình đơn lẻ. */
    public function oj(Request $request, int $question): View
    {
        $user = $request->user();

        return view('student.assessment.oj', $this->assessmentService->buildOjData($user, $question));
    }

    /**
     * student.assessment.result (STU-08/09) — kết quả bài làm.
     * SỬA 19/8 (Giai đoạn 2): chọn view result-pdf khi đề là content_mode=pdf_answer_sheet —
     * xem AssessmentService::buildResultData().
     */
    public function result(Request $request, int $attempt): View
    {
        $user = $request->user();
        $data = $this->assessmentService->buildResultData($user, $attempt);

        $view = $data['isPdfMode'] ? 'student.assessment.result-pdf' : 'student.assessment.result';

        return view($view, $data);
    }

    /**
     * student.assessment.pdf.file (STU-05, Giai đoạn 2 — đề PDF) — phục vụ PDF đề ($which=
     * 'exam') hoặc PDF lời giải ($which='solution') xem trực tiếp trên web (16/8 mục 3: "PDF
     * xem trên web, không cần cho tải trực tiếp"). Quyền xem được kiểm tra ở tầng Service
     * (AssessmentService::streamPdfFile()) chứ không chỉ dựa vào việc ẩn link ở view.
     */
    public function pdfFile(Request $request, int $assessment, string $which): StreamedResponse
    {
        $user = $request->user();

        return $this->assessmentService->streamPdfFile($user, $assessment, $which);
    }
}
