<?php

namespace App\Services\Student;

use App\Enums\AnswerSheetQuestionType;
use App\Enums\PublishAnswerRule;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\AttemptCodingItem;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptAnswerRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Services\AttemptService;
use App\Services\CodeJudgingService;
use App\Services\PdfAttemptService;
use App\Services\ReviewEligibilityService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentService
{
    public function __construct(
        private AssessmentRepositoryInterface $assessments,
        private AttemptRepositoryInterface $attempts,
        private AttemptAnswerRepositoryInterface $attemptAnswers,
        private QuestionRepositoryInterface $questions,
        private ClassRoomRepositoryInterface $classRooms,
        private AssignmentRepositoryInterface $assignments,
        private AttemptService $attemptService,
        private ReviewEligibilityService $reviewEligibility,
        // SỬA 19/8 (Giai đoạn 2 — đề PDF, 16/8 mục 1.2/6): chấm/lưu câu trả lời cho Attempt
        // của đề content_mode=pdf_answer_sheet — xem App\Services\PdfAttemptService để biết vì
        // sao tách riêng khỏi AttemptService (không đụng Question).
        private PdfAttemptService $pdfAttemptService,
        // SỬA 18/9 (2) (khách: "chỗ bắt đầu làm đề, chỗ chạy test không chạy được") — chạy thử
        // mã với dữ liệu vào tự gõ ngay trong không gian làm đề, xem runCodeOnce().
        private CodeJudgingService $codeJudging,
    ) {}

    /**
     * student.assessment.take (STU-05) — không gian làm bài thật (không còn là UI tĩnh):
     * mở/tiếp tục 1 Attempt thật qua App\Services\AttemptService, hiển thị đúng nội dung
     * từng câu (MCQ/điền đáp án/lập trình) + câu trả lời đã lưu nếu đang làm dở (resume).
     * $assignmentId (tuỳ chọn) cho biết học sinh vào từ "Bài được giao" của lớp nào — nếu
     * có, lượt làm bài mới sẽ mang class_room_id + kích hoạt điểm danh tự động (8.2).
     *
     * SỬA 19/8 (Giai đoạn 2): đề content_mode=pdf_answer_sheet rẽ hẳn sang buildPdfTakeData()
     * — cùng route/view layout ngoài (student.assessment.take.blade.php chọn theo isPdfMode),
     * KHÔNG tạo route riêng, để mọi nơi đã link sẵn student.assessment.take (trang chủ, Bài
     * được giao, Luyện tập, Cuộc thi...) không cần sửa gì thêm.
     */
    public function buildTakeData(User $user, int $assessmentId, ?int $assignmentId = null): array
    {
        $assessmentModel = $this->assessments->withItemsAndQuestions($assessmentId);
        abort_if($assessmentModel === null, 404);

        $assignment = $assignmentId !== null ? $this->assignments->find($assignmentId) : null;
        if ($assignment !== null) {
            abort_unless((int) $assignment->assessment_id === $assessmentModel->id, 404);
        }

        if ($assessmentModel->isPdfMode()) {
            return $this->buildPdfTakeData($user, $assessmentModel, $assignment);
        }

        $attempt = $this->attemptService->startOrResume($user, $assessmentModel, $assignment);

        // Hết giờ trong lúc học sinh không mở trang (đóng tab, mất mạng, rớt wifi giữa
        // chừng...) — tự nộp NGAY khi họ quay lại thay vì hiện lại y hệt trang làm bài như
        // chưa có gì xảy ra (App\Http\Controllers\Student\AssessmentController::take() kiểm
        // tra lại $attempt->status sau lời gọi này để điều hướng sang trang kết quả nếu vừa
        // được tự nộp ở đây).
        $attempt = $this->attemptService->finalizeIfExpired($attempt);

        $existingAnswers = $this->attemptAnswers->forAttempt($attempt->id);

        $questions = $assessmentModel->items->values()->map(function ($item, $idx) use ($existingAnswers) {
            $question = $item->question;
            $existing = $existingAnswers->get($question->id);

            return [
                'no' => $idx + 1,
                'questionId' => $question->id,
                'type' => $question->type->value,
                'points' => $item->effectivePoints(),
                'title' => $question->title,
                'body' => $question->body,
                'options' => $question->grading_config['options'] ?? [],
                'selectedOption' => $existing?->answer['selected_option'] ?? null,
                'textAnswer' => $existing?->answer['text'] ?? null,
                'codeSource' => $existing?->code_source,
                'language' => $existing?->language,
                'status' => $existing !== null ? 'answered' : 'unanswered',

                // ── SỬA 18/9 (dựng lại giao diện phòng thi theo bản mẫu) ──────────────────
                // 3 khoá THÊM, thuần hiển thị — không đụng gì tới luồng lưu nháp/nộp/chấm.
                // 'kind' gom 4 dạng câu của hệ thống về đúng 3 nhóm mà bản mẫu vẽ giao diện
                // (choice/fill/code), để Blade không phải tự đoán lại ở nhiều chỗ.
                'kind' => match ($question->type) {
                    QuestionType::Coding => 'code',
                    QuestionType::FillBlank => 'fill',
                    default => 'choice',
                },
                'typeLabel' => match ($question->type) {
                    QuestionType::Coding => 'Lập trình',
                    QuestionType::FillBlank => 'Điền đáp án',
                    QuestionType::Composite => 'Nhiều phần',
                    default => 'Trắc nghiệm',
                },
                // Tab "Đề bài PDF" của bản mẫu. Nguồn thật: tệp đính kèm kind='statement' của
                // câu hỏi (Question::attachmentInfo). CHỈ 'statement' — 'solution'/'reference'
                // tuyệt đối không lộ cho học sinh, xem ghi chú ở chính Question::attachmentInfo()
                // và Student\PracticeByQuestionController::statement() (route dùng lại ở đây).
                'statementPdfUrl' => $question->attachmentInfo('statement') !== null
                    ? route('student.practiceByQuestion.statement', $question->id)
                    : null,
            ];
        })->all();

        return [
            'isPdfMode' => false,
            'assessmentModel' => $assessmentModel,
            'attempt' => $attempt,
            'questions' => $questions,
            // SỬA 18/9 — dòng nhãn nhỏ trên đầu phòng thi của bản mẫu ("EXAM_HSG_01 · 100 ĐIỂM").
            // Lấy đúng 2 cột đã có của đề; đề chưa đặt mã thì Blade tự ẩn phần mã.
            'examCode' => $assessmentModel->exam_code,
            'totalPoints' => $assessmentModel->total_points
                ?? collect($questions)->sum('points'),
            // Đồng hồ đếm ngược ở client tính từ 2 mốc giờ MÁY CHỦ này (không dùng giờ máy của
            // học sinh) — null nếu đề không giới hạn thời gian (không có duration_minutes lẫn
            // không giao qua assignment có khung giờ). Việc CHẶN THẬT khi hết giờ luôn nằm ở
            // server (AttemptService::isExpired()/saveAnswer()) — đồng hồ này chỉ để hiển thị.
            'deadlineAt' => $this->attemptService->deadlineFor($attempt)?->toIso8601String(),
            'serverNow' => now()->toIso8601String(),
        ];
    }

    /**
     * SỬA 19/8 — nhánh riêng của buildTakeData() cho đề content_mode=pdf_answer_sheet. Vòng
     * đời mở/tiếp tục lượt làm bài DÙNG CHUNG AttemptService (startOrResume/finalizeIfExpired/
     * deadlineFor không phụ thuộc Question); chỉ nội dung hiển thị (answerRows/codingRows) và
     * việc lưu/nộp câu trả lời (xem saveDraftAnswers()/submitAttempt() bên dưới) là khác nhau.
     */
    private function buildPdfTakeData(User $user, Assessment $assessmentModel, ?Assignment $assignment): array
    {
        $attempt = $this->attemptService->startOrResume($user, $assessmentModel, $assignment);
        $attempt = $this->attemptService->finalizeIfExpired($attempt);

        $assessmentModel->load(['answerKeys', 'codingItems.testCases']);
        $attempt->load(['answerKeys', 'codingItems']);

        $existingAnswerKeys = $attempt->answerKeys->keyBy('answer_key_id');
        $existingCodingItems = $attempt->codingItems->keyBy('coding_item_id');

        $answerRows = $assessmentModel->answerKeys->map(function ($answerKey) use ($existingAnswerKeys) {
            $existing = $existingAnswerKeys->get($answerKey->id);

            return [
                'answerKeyId' => $answerKey->id,
                'no' => $answerKey->question_no,
                'type' => $answerKey->question_type->value,
                'typeLabel' => $answerKey->question_type->label(),
                'points' => $answerKey->points,
                'submittedAnswer' => $existing?->submitted_answer,
                'answered' => $existing !== null,
                // SỬA 9/9 (dạng "Câu nhiều ý") — màn làm bài cần biết câu này có những ý nào và
                // MỖI Ý nhập kiểu gì để dựng đúng ô trả lời. CHỈ trả tên ý + kiểu ý; TUYỆT ĐỐI
                // không đưa 'value' (đáp án đúng) ra màn học sinh.
                'parts' => $answerKey->question_type === AnswerSheetQuestionType::MultiPart
                    ? collect((array) $answerKey->correct_answer)
                        ->map(fn ($spec, $part) => [
                            'part' => (string) $part,
                            'type' => is_array($spec) ? ($spec['type'] ?? AnswerSheetQuestionType::ShortAnswer->value) : AnswerSheetQuestionType::ShortAnswer->value,
                        ])
                        ->values()->all()
                    : [],
            ];
        })->values()->all();

        $codingRows = $assessmentModel->codingItems->map(function ($codingItem) use ($existingCodingItems) {
            $existing = $existingCodingItems->get($codingItem->id);

            return [
                'codingItemId' => $codingItem->id,
                'code' => $codingItem->code,
                'title' => $codingItem->title,
                'pdfPage' => $codingItem->pdf_page,
                'allowedLanguages' => $codingItem->allowed_languages ?? [],
                'points' => $codingItem->points,
                'codeSource' => $existing?->code_source,
                'language' => $existing?->language,
                'answered' => $existing !== null,
            ];
        })->values()->all();

        return [
            'isPdfMode' => true,
            'assessmentModel' => $assessmentModel,
            'attempt' => $attempt,
            'answerRows' => $answerRows,
            'codingRows' => $codingRows,
            // Xem PDF đề qua route riêng (streamPdfFile()) — KHÔNG lộ đường lưu file thật trên
            // đĩa ra client, và luôn kiểm tra lại quyền sở hữu attempt ở đó (16 mục 3).
            'pdfUrl' => route('student.assessment.pdf.file', ['assessment' => $assessmentModel->id, 'which' => 'exam']),
            'deadlineAt' => $this->attemptService->deadlineFor($attempt)?->toIso8601String(),
            'serverNow' => now()->toIso8601String(),
        ];
    }

    /**
     * student.assessment.take.save (POST "Lưu nháp") — lưu câu trả lời hiện có, KHÔNG nộp
     * bài. $answersInput dạng ['<questionId>' => ['selected_option'=>..|'text'=>..|
     * 'code_source'=>..,'language'=>..]], chỉ những câu thuộc đúng đề của attempt này mới
     * được lưu (không tin question_id client gửi lên — 16 mục 3).
     *
     * SỬA 19/8 (Giai đoạn 2): đề PDF gửi $answersInput dạng ['answer_keys'=>[...],
     * 'coding_items'=>[...]] (xem PdfAttemptService::saveDraft()) — rẽ nhánh theo
     * $attempt->assessment->isPdfMode(), route/tên hàm giữ nguyên cho cả 2 chế độ.
     *
     * @throws \Illuminate\Validation\ValidationException nếu attempt đã kết thúc.
     */
    public function saveDraftAnswers(User $user, int $attemptId, array $answersInput): Attempt
    {
        $attempt = $this->ownedAttemptOrFail($user, $attemptId);

        if ($attempt->assessment->isPdfMode()) {
            return $this->pdfAttemptService->saveDraft(
                $attempt,
                $answersInput['answer_keys'] ?? [],
                $answersInput['coding_items'] ?? [],
            );
        }

        $assessmentModel = $this->assessments->withItemsAndQuestions($attempt->assessment_id);

        foreach ($assessmentModel->items as $item) {
            $raw = $answersInput[$item->question_id] ?? null;
            if ($raw === null) {
                continue;
            }
            $this->attemptService->saveAnswer($attempt, $item->question, $raw);
        }

        return $attempt;
    }

    /**
     * student.assessment.take.submit (POST "Nộp bài") — lưu mọi câu trả lời gửi kèm rồi
     * khoá lượt làm bài. SỬA 19/8: khoá lượt làm bài qua đúng service của từng chế độ —
     * PdfAttemptService::submit() (đề PDF) hay AttemptService::submit() (đề câu hỏi rời).
     *
     * @throws \Illuminate\Validation\ValidationException nếu attempt đã nộp trước đó.
     */
    public function submitAttempt(User $user, int $attemptId, array $answersInput): Attempt
    {
        $this->saveDraftAnswers($user, $attemptId, $answersInput);

        $attempt = $this->ownedAttemptOrFail($user, $attemptId);

        if ($attempt->assessment->isPdfMode()) {
            return $this->pdfAttemptService->submit($attempt);
        }

        return $this->attemptService->submit($attempt);
    }

    /**
     * student.assessment.take.run (SỬA 18/9 (2), khách: "chỗ bắt đầu làm đề khi click vào để
     * làm á, chỗ chạy test không chạy được") — CHẠY THỬ mã của 1 câu trong đề với dữ liệu vào
     * học sinh tự gõ ở ô Input.
     *
     * KHÔNG chấm điểm, KHÔNG đụng bài làm đã lưu, không tính là một lần nộp — chạy bao nhiêu
     * lần cũng được. Chấm thật vẫn chỉ xảy ra lúc Nộp đề (AttemptService::gradeCoding()).
     *
     * Ba chốt an toàn, làm ở ĐÂY chứ không tin giao diện:
     *   1. Lượt làm bài phải của CHÍNH người đang đăng nhập (ownedAttemptOrFail).
     *   2. Câu hỏi phải THUỘC đúng đề của lượt đó — nếu không, ai cũng có thể mượn màn này để
     *      chạy mã trên máy chấm cho một câu bất kỳ trong hệ thống.
     *   3. Hết giờ/đã nộp thì thôi — cùng luật với ô tự lưu.
     *
     * @param  array{question_id: int|string, code_source?: string, language?: string, stdin?: string}  $data
     * @return array{ok: bool, message?: string, ranCleanly?: bool, statusLabel?: string, output?: string, stderr?: ?string, compileOutput?: ?string, time?: ?string, memory?: ?int}
     */
    public function runCodeOnce(User $user, int $attemptId, array $data): array
    {
        $attempt = $this->ownedAttemptOrFail($user, $attemptId);

        if ($attempt->submitted_at !== null) {
            return ['ok' => false, 'message' => 'Đề đã nộp — không chạy thử được nữa.'];
        }

        // Lượt TỰ LUYỆN theo câu không thuộc đề nào (assessment_id = null, xem
        // Student\PracticeByQuestionService::recordSubmission()). Không chặn ở đây thì truyền id
        // của một lượt như vậy vào sẽ làm withItemsAndQuestions(null) ném TypeError -> 500.
        // Màn luyện theo câu có đường chạy thử RIÊNG (student.practiceByQuestion.run).
        if ($attempt->assessment_id === null) {
            return ['ok' => false, 'message' => 'Lượt làm bài này không gắn với đề nào.'];
        }

        $assessmentModel = $this->assessments->withItemsAndQuestions($attempt->assessment_id);
        $item = $assessmentModel?->items->firstWhere('question_id', (int) $data['question_id']);

        if ($item === null || $item->question === null) {
            return ['ok' => false, 'message' => 'Câu hỏi này không thuộc đề đang làm.'];
        }

        if ($item->question->type !== QuestionType::Coding) {
            return ['ok' => false, 'message' => 'Chỉ câu Lập trình mới chạy thử được.'];
        }

        // Kiểm 2 điều kiện này TRƯỚC khi gọi máy chấm để báo đúng lý do — gọi rồi mới bắt ngoại
        // lệ thì mọi lỗi đều ra chung một câu "không kết nối được máy chấm", sai sự thật.
        if (trim((string) ($data['code_source'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Chưa có mã nguồn để chạy — viết code rồi bấm lại.'];
        }

        if (config('judge0.languages.'.($data['language'] ?? '')) === null) {
            return ['ok' => false, 'message' => 'Ngôn ngữ này chưa chạy được trên máy chấm.'];
        }

        $config = $item->question->grading_config ?? [];

        try {
            $result = $this->codeJudging->run(
                (string) $data['code_source'],
                $data['language'],
                (string) ($data['stdin'] ?? ''),
                (int) ($config['time_limit_ms'] ?? 5000),
                (int) ($config['memory_limit_mb'] ?? 256) * 1024,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'Chạy thử thất bại ở câu #'.$item->question_id.' trong đề #'.$attempt->assessment_id.' (Judge0 không tới được)',
                ['exception' => $e],
            );

            return ['ok' => false, 'message' => 'Không kết nối được máy chấm — báo giáo viên/quản trị viên kiểm tra máy chấm giúp bạn.'];
        }

        return ['ok' => true] + $result;
    }

    private function ownedAttemptOrFail(User $user, int $attemptId): Attempt
    {
        $attempt = $this->attempts->find($attemptId);
        abort_if($attempt === null, 404);
        abort_unless($attempt->user_id === $user->id, 403);

        return $attempt;
    }

    /** student.assessment.oj (STU-06/07) — làm câu lập trình đơn lẻ. */
    public function buildOjData(User $user, int $questionId): array
    {
        $questionModel = $this->questions->findOrFail($questionId);

        $submissions = $this->attemptAnswers->forQuestionAndUser($questionModel->id, $user->id, 10)
            ->map(fn ($answer) => [
                'time' => $answer->graded_at?->diffForHumans() ?? $answer->updated_at?->diffForHumans(),
                'verdict' => $answer->verdict?->value ?? 'pending',
                'tone' => $answer->verdict?->isFinal()
                    ? ($answer->verdict?->value === 'accepted' ? 'success' : 'danger')
                    : 'info',
            ])->all();

        return [
            'questionModel' => $questionModel,
            'submissions' => $submissions,
        ];
    }

    /**
     * student.assessment.result (STU-08/09) — kết quả bài làm.
     *
     * SỬA 19/8 (Giai đoạn 2): đề content_mode=pdf_answer_sheet rẽ sang buildPdfResultData() —
     * cùng route student.assessment.result, view chọn theo isPdfMode (giống buildTakeData()).
     */
    public function buildResultData(User $user, int $attemptId): array
    {
        $attemptModel = $this->attempts->withAnswersAndAssessment($attemptId);
        abort_if($attemptModel === null, 404);

        abort_unless(
            $attemptModel->user_id === $user->id || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN),
            403
        );

        // SỬA 18/9 — lượt TỰ LUYỆN theo câu không thuộc đề nào (assessment_id = null, xem
        // Student\PracticeByQuestionService::recordSubmission()). Cả trang này dựng quanh một
        // đề (điểm tối đa, chế độ PDF, danh sách câu trong đề) nên không có đề là không dựng
        // được — trả 404 thay vì vỡ ở dòng dưới. Lịch sử làm bài đã trỏ lượt tự luyện về trang
        // Luyện tập, đây chỉ là chốt chặn cho ai gõ thẳng URL.
        abort_if($attemptModel->assessment === null, 404);

        if ($attemptModel->assessment->isPdfMode()) {
            return $this->buildPdfResultData($user, $attemptModel);
        }

        $isFinal = ! $attemptModel->is_provisional;
        $score = $attemptModel->total_score;
        $total = $attemptModel->assessment->total_points ?? null;

        $breakdown = $attemptModel->answers->map(function ($answer, $idx) {
            $verdictLabel = match ($answer->verdict?->value) {
                'accepted' => 'Đúng',
                'wrong_answer' => 'Sai',
                'pending', 'queued', 'judging' => 'Đang chấm',
                default => $answer->verdict?->value ?? '—',
            };
            $tone = match (true) {
                $answer->verdict?->value === 'accepted' => 'success',
                in_array($answer->verdict?->value, ['pending', 'queued', 'judging'], true) => 'info',
                $answer->verdict === null => 'neutral',
                default => 'danger',
            };

            return [
                'no' => $idx + 1,
                'type' => $answer->question?->type?->value ?? '',
                'verdict' => $verdictLabel,
                'points' => $answer->score !== null ? (string) $answer->score : '—',
                'tone' => $tone,
            ];
        })->all();

        $reviewCta = $this->reviewCtaTarget($user, $attemptModel);

        return [
            'isPdfMode' => false,
            'attemptModel' => $attemptModel,
            'isFinal' => $isFinal,
            'score' => $score,
            'total' => $total,
            'breakdown' => $breakdown,
            'eligibleForReview' => $reviewCta !== null,
            // type/targetId đúng đối tượng CỦA CHÍNH lượt làm bài này (lớp hoặc học liệu) —
            // trước đây view hardcode thẳng route('reviews.form', ['type'=>'material','id'=>1])
            // nên nút "Đánh giá" luôn trỏ nhầm sang học liệu #1 bất kể học sinh vừa làm đề gì.
            'reviewType' => $reviewCta['type'] ?? null,
            'reviewTargetId' => $reviewCta['targetId'] ?? null,
        ];
    }

    /**
     * SỬA 19/8 — nhánh riêng của buildResultData() cho đề content_mode=pdf_answer_sheet.
     * $attemptModel đã qua kiểm tra quyền sở hữu ở buildResultData() — hàm này chỉ lo dựng
     * dữ liệu hiển thị. Lời giải (solution_pdf_path) chỉ lộ link khi publish_answer_rule cho
     * phép NGAY LÚC NÀY (xem answersPublishedNow()) — không chỉ dựa vào có file hay không.
     */
    private function buildPdfResultData(User $user, Attempt $attemptModel): array
    {
        $attemptModel->load(['answerKeys.answerKey', 'codingItems.codingItem']);

        $isFinal = ! $attemptModel->is_provisional;
        $score = $attemptModel->total_score;
        $total = ($attemptModel->assessment->answerKeys->sum('points') ?? 0)
            + ($attemptModel->assessment->codingItems->sum('points') ?? 0);
        $total = $total > 0 ? $total : null;

        $answerBreakdown = $attemptModel->answerKeys
            ->sortBy(fn ($a) => $a->answerKey?->question_no ?? 0)
            ->values()
            ->map(fn ($a) => [
                'no' => $a->answerKey?->question_no,
                'type' => $a->answerKey?->question_type?->label() ?? '',
                'verdict' => $a->is_correct ? 'Đúng' : 'Sai',
                'tone' => $a->is_correct ? 'success' : 'danger',
                'points' => $a->score !== null ? (string) $a->score : '—',
            ])->all();

        $codingBreakdown = $attemptModel->codingItems->map(function ($c) {
            $verdictLabel = match (true) {
                ! $c->verdict->isFinal() => 'Đang chấm',
                $c->verdict->value === 'accepted' => 'Đúng',
                default => 'Sai',
            };
            $tone = match (true) {
                ! $c->verdict->isFinal() => 'info',
                $c->verdict->value === 'accepted' => 'success',
                default => 'danger',
            };

            /*
             * SỬA 19/9 (7) — TỈ LỆ AC của bài lập trình (số test đã qua / tổng số test), lấy từ
             * 2 cột ghi lúc chấm (PdfAttemptService::gradePendingCodingItems()).
             *
             * null = bài nộp từ trước khi có 2 cột đó, hoặc máy chủ chưa chạy migration — view
             * sẽ ẩn hẳn phần tỉ lệ thay vì hiện "0/0 test" gây hiểu nhầm là bài không có test.
             */
            $totalTests = $c->total_tests !== null ? (int) $c->total_tests : null;
            $passedTests = $c->passed_tests !== null ? (int) $c->passed_tests : null;

            return [
                'code' => $c->codingItem?->code,
                'title' => $c->codingItem?->title,
                'verdict' => $verdictLabel,
                'tone' => $tone,
                'points' => $c->score !== null ? (string) $c->score : '—',
                'passedTests' => $passedTests,
                'totalTests' => $totalTests,
                'testPercent' => ($totalTests !== null && $totalTests > 0 && $passedTests !== null)
                    ? (int) round($passedTests / $totalTests * 100)
                    : null,
            ];
        })->all();

        $solutionUrl = ($attemptModel->assessment->solution_pdf_path && $this->answersPublishedNow($attemptModel->assessment, $attemptModel))
            ? route('student.assessment.pdf.file', ['assessment' => $attemptModel->assessment_id, 'which' => 'solution'])
            : null;

        $reviewCta = $this->reviewCtaTarget($user, $attemptModel);

        return [
            'isPdfMode' => true,
            'attemptModel' => $attemptModel,
            'isFinal' => $isFinal,
            'score' => $score,
            'total' => $total,
            'answerBreakdown' => $answerBreakdown,
            'codingBreakdown' => $codingBreakdown,
            'examUrl' => route('student.assessment.pdf.file', ['assessment' => $attemptModel->assessment_id, 'which' => 'exam']),
            'solutionUrl' => $solutionUrl,
            'eligibleForReview' => $reviewCta !== null,
            'reviewType' => $reviewCta['type'] ?? null,
            'reviewTargetId' => $reviewCta['targetId'] ?? null,
        ];
    }

    /**
     * Đề PDF chỉ lộ file lời giải khi quy tắc công bố đáp án của đề cho phép NGAY LÚC NÀY —
     * cùng ý nghĩa với publish_answer_rule dùng cho đề câu hỏi rời, áp dụng lại cho đề PDF
     * (16/8 mục 6: "lời giải công bố theo đúng quy tắc đã chọn khi tạo đề").
     */
    private function answersPublishedNow(Assessment $assessment, Attempt $attemptModel): bool
    {
        return match ($assessment->publish_answer_rule) {
            PublishAnswerRule::Immediately => true,
            PublishAnswerRule::AfterDeadline => $attemptModel->submitted_at !== null,
            default => false,
        };
    }

    /**
     * student.assessment.pdf.file (STU-05, đề PDF) — phục vụ file PDF đề/lời giải cho ĐÚNG
     * học sinh đang/đã làm đề đó. $which='exam' luôn xem được nếu đã có ít nhất 1 lượt làm
     * bài; $which='solution' chỉ xem được khi answersPublishedNow() cho phép — chặn ở tầng
     * Service này (không chỉ ẩn link ở view) vì URL có thể bị đoán/chia sẻ (16 mục 3).
     */
    public function streamPdfFile(User $user, int $assessmentId, string $which): StreamedResponse
    {
        $assessmentModel = $this->assessments->find($assessmentId);
        abort_if($assessmentModel === null || ! $assessmentModel->isPdfMode(), 404);

        $attemptModel = $this->attempts->query()
            ->where('user_id', $user->id)
            ->where('assessment_id', $assessmentModel->id)
            ->latest('started_at')
            ->first();
        abort_if($attemptModel === null, 403);

        if ($which === 'solution') {
            abort_unless($this->answersPublishedNow($assessmentModel, $attemptModel), 403);
        }

        $path = $which === 'solution' ? $assessmentModel->solution_pdf_path : $assessmentModel->pdf_path;
        abort_if(blank($path), 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * 9.x: CTA đánh giá cuối trang kết quả — attempt gắn với lớp (class_room_id) thì xét
     * điều kiện đánh giá LỚP; ngược lại (tự luyện/đề độc lập) xét điều kiện đánh giá HỌC LIỆU
     * qua sản phẩm chứa đề (Assessment -> Material -> Product). Trả về type/targetId ĐÚNG như
     * App\Services\Review\ReviewService mong đợi (type=class -> id=ClassRoom.id, type=material
     * -> id=Material.id, KHÔNG phải Product.id) để nút đánh giá ở trang kết quả trỏ đúng đối
     * tượng học sinh vừa học/làm, thay vì hardcode.
     *
     * @return array{type: string, targetId: int}|null null nếu chưa đủ điều kiện đánh giá.
     */
    private function reviewCtaTarget(User $user, Attempt $attemptModel): ?array
    {
        if ($attemptModel->class_room_id !== null) {
            $classRoom = $this->classRooms->find($attemptModel->class_room_id);

            if ($classRoom === null || ! $this->reviewEligibility->eligibleForClassReview($user, $classRoom)->allowed) {
                return null;
            }

            return ['type' => 'class', 'targetId' => $classRoom->id];
        }

        $material = $attemptModel->assessment?->materials?->first();
        $product = $material?->product;

        if ($material === null || $product === null || ! $this->reviewEligibility->eligibleForMaterialReview($user, $product)->allowed) {
            return null;
        }

        return ['type' => 'material', 'targetId' => $material->id];
    }
}
