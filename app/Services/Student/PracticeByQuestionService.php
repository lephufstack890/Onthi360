<?php

namespace App\Services\Student;

use App\Models\Question;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Services\CodeJudgingService;
use App\Services\QuestionGrader;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Throwable;

/**
 * Route: student.practiceByQuestion.* | Giai đoạn 6 — "Luyện tập theo câu": học sinh chọn
 * tag/chuyên đề (+ tuỳ chọn dạng câu) rồi luyện TỪNG CÂU một, xem đáp án ngay, qua câu tiếp
 * theo — khác hẳn luồng "làm đề" (App\Services\AttemptService) ở chỗ không gắn với 1
 * Assessment cụ thể nào.
 *
 * CỐ Ý KHÔNG tạo Attempt/AttemptAnswer trong DB cho luồng này — đây là bài tập TỰ DO ngoài
 * đề, không phải bài làm chính thức cần lưu vết/tính vào lịch sử điểm số (10.4 lịch sử chỉ
 * nói tới Attempt của Assessment). Toàn bộ tiến trình (danh sách câu đã xáo trộn, đang ở câu
 * thứ mấy, đã đúng bao nhiêu) lưu tạm ở session — mất khi hết phiên/đổi trình duyệt, CHẤP
 * NHẬN ĐƯỢC vì bản chất là luyện nháp, không phải nhu cầu "xem lại lịch sử luyện tập" (nếu
 * sau này cần lưu vết thật, đó là 1 bảng mới ngoài phạm vi Giai đoạn 6).
 *
 * Chỉ chọn câu đã phát hành — xem QuestionRepositoryInterface::idsForPractice(). SỬA 24/8
 * (v3) — khách chốt: dùng CẢ câu hỏi kho riêng giáo viên, không chỉ Kho chung nữa
 * (idsForPractice() đã bỏ điều kiện owner_type='shared') — "đã phát hành" vẫn là điều kiện
 * chặn duy nhất, câu Nháp của giáo viên không lọt ra được.
 * SỬA 24/8 (v4) — khách chốt: nhận cả câu Lập trình — hệ thống vẫn CHƯA có sandbox chấm code
 * thật (cùng lý do AttemptService chỉ ghi nhận "Queued" cho Lập trình, xem docblock đó), nên
 * answer() KHÔNG tự chấm đúng/sai cho câu Lập trình — chỉ ghi nhận đã làm (answered++, không
 * cộng vào correct), xem 'gradable' trong $state['feedback'].
 */
class PracticeByQuestionService
{
    private const SESSION_KEY = 'practice_by_question';

    public function __construct(
        private readonly QuestionRepositoryInterface $questions,
        private readonly TagRepositoryInterface $tags,
        // SỬA (nối máy chấm Judge0 thật) — xem judgeCodingAnswer() bên dưới.
        private readonly CodeJudgingService $codeJudging,
    ) {}

    /**
     * student.practiceByQuestion.setup — màn chọn tag/dạng câu trước khi bắt đầu.
     * SỬA 24/8 — đổi allOrderedByName() → allWithPracticeQuestions(): chỉ mời chọn chuyên đề
     * THỰC SỰ có câu hỏi thoả idsForPractice() (đã phát hành — SỬA 24/8 v3: kể cả câu thuộc
     * kho riêng giáo viên; v4: kể cả dạng Lập trình) — chọn 1 tag chỉ gắn cho câu chưa phát
     * hành chắc chắn ra 0 câu ở start(), dù tag đó "có dữ liệu" theo nghĩa khác.
     */
    public function setupData(): array
    {
        return ['allTags' => $this->tags->allWithPracticeQuestions()];
    }

    /**
     * student.practiceByQuestion.start — xáo trộn (shuffle) toàn bộ ID câu phù hợp bộ lọc rồi
     * lưu vào session. false nếu không có câu nào khớp (controller báo lỗi, không bắt đầu).
     */
    public function start(array $tagIds, ?string $type): bool
    {
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
        $ids = $this->questions->idsForPractice($type, $tagIds);

        if ($ids === []) {
            return false;
        }

        shuffle($ids);

        Session::put(self::SESSION_KEY, [
            'question_ids' => $ids,
            'index' => 0,
            'answered' => 0,
            'correct' => 0,
            'filters' => ['tag_ids' => $tagIds, 'type' => $type],
            'feedback' => null,
        ]);

        return true;
    }

    /**
     * SỬA 31/8 ("Làm bài" 1 bài tập cụ thể của sản phẩm, từ "Tài liệu của tôi") — mở phiên
     * luyện CHỈ 1 câu, khác start() ở trên (xáo trộn cả pool theo tag/dạng câu). Controller gọi
     * hàm này ĐÃ tự kiểm tra quyền sở hữu sản phẩm trước (AccessGateService::canAccessProduct())
     * — hàm này không lặp lại kiểm tra đó, chỉ lo phần "mở phiên luyện".
     *
     * $returnUrl lưu lại để playData()/stop() biết đường quay về ĐÚNG trang sản phẩm (Tài liệu
     * của tôi) khi xong/dừng, thay vì trang "Luyện tập theo câu" chung — xem 'mode' ở
     * playData() và stop() bên dưới.
     */
    public function startForQuestion(int $questionId, ?string $returnUrl = null): void
    {
        Session::put(self::SESSION_KEY, [
            'question_ids' => [$questionId],
            'index' => 0,
            'answered' => 0,
            'correct' => 0,
            'filters' => null,
            'feedback' => null,
            'mode' => 'single_question',
            'returnUrl' => $returnUrl,
        ]);
    }

    /**
     * student.practiceByQuestion.play — null nếu chưa có phiên luyện đang mở (controller
     * đưa về màn setup). 'finished' => true khi đã luyện hết toàn bộ câu trong phiên.
     */
    public function playData(): ?array
    {
        $state = Session::get(self::SESSION_KEY);

        if (! is_array($state) || empty($state['question_ids'])) {
            return null;
        }

        $total = count($state['question_ids']);
        $index = $state['index'];

        if ($index >= $total) {
            return [
                'finished' => true,
                'total' => $total,
                'correct' => $state['correct'],
                'answered' => $state['answered'],
                'mode' => $state['mode'] ?? null,
                'returnUrl' => $state['returnUrl'] ?? null,
            ];
        }

        $question = Question::with('tags')->find($state['question_ids'][$index]);

        if ($question === null) {
            $this->advance();

            return $this->playData();
        }

        return [
            'finished' => false,
            'question' => $question,
            'options' => $question->grading_config['options'] ?? [],
            'compositeParts' => $question->type->value === 'composite'
                ? $this->sanitizedCompositeParts($question->grading_config['parts'] ?? [])
                : [],
            'assets' => collect($question->metadata['assets'] ?? [])->map(fn ($a) => [
                'id' => $a['id'] ?? null,
                'kind' => $a['kind'] ?? 'file',
                'filename' => $a['filename'] ?? null,
                'altText' => $a['alt_text'] ?? null,
                'transcript' => $a['transcript'] ?? null,
                'url' => route('student.practiceByQuestion.asset', [$question->id, $a['id'] ?? '']),
            ])->all(),
            'progress' => ['current' => $index + 1, 'total' => $total, 'correct' => $state['correct'], 'answered' => $state['answered']],
            'feedback' => $state['feedback'],
            'mode' => $state['mode'] ?? null,
            'returnUrl' => $state['returnUrl'] ?? null,
        ];
    }

    /**
     * Rút gọn grading_config['parts'] của câu Composite để đưa ra view DỰNG FORM trả lời — chỉ
     * giữ những gì cần để HIỆN câu hỏi (code phần, dạng con, phương án chọn nếu là single_choice,
     * điểm), bỏ hẳn 'correct_answer'/'accepted_answers'/'rubric' (đáp án đúng — chỉ lộ ra sau khi
     * đã trả lời, qua $state['feedback'], xem gradeCompositeParts() bên dưới).
     *
     * @return array<int, array{code:string, responseType:string, choices:array, points:float}>
     */
    private function sanitizedCompositeParts(array $parts): array
    {
        return array_map(fn (array $part) => [
            'code' => $part['code'] ?? '',
            'responseType' => $part['response_type'] ?? '',
            'choices' => $part['choices'] ?? [],
            'points' => $part['points'] ?? 0,
        ], $parts);
    }

    public function answer(array $data): bool
    {
        $state = Session::get(self::SESSION_KEY);

        if (! is_array($state) || empty($state['question_ids']) || $state['index'] >= count($state['question_ids'])) {
            return false;
        }

        $question = Question::find($state['question_ids'][$state['index']]);

        if ($question === null) {
            return false;
        }

        $isCoding = $question->type->value === 'coding';
        $isComposite = $question->type->value === 'composite';

        $codingResult = $isCoding ? $this->judgeCodingAnswer($question, $data) : null;

        $compositeResult = null;
        $isCorrect = match ($question->type->value) {
            'mcq' => QuestionGrader::isMcqCorrect($question, $data['selected_option'] ?? null),
            'fill_blank' => QuestionGrader::isFillBlankCorrect($question, (string) ($data['text'] ?? '')),
            'composite' => ($compositeResult = $this->gradeCompositeParts($question, $data['parts'] ?? []))['allGradableCorrect'],
            'coding' => $codingResult['isAccepted'] ?? false,
            default => false,
        };

        $gradable = ($isCoding ? $codingResult !== null : true) && ! ($isComposite && $compositeResult['hasUngraded']);

        if ($state['feedback'] === null) {
            $state['answered']++;
            if ($isCorrect && $gradable) {
                $state['correct']++;
            }
        }

        $state['feedback'] = [
            'isCorrect' => $isCorrect,
            'gradable' => $gradable,
            'correctOptions' => $question->grading_config['correct_options'] ?? [],
            'acceptedAnswers' => $question->grading_config['accepted_answers'] ?? [],
            'yourSelectedOption' => $data['selected_option'] ?? null,
            'yourText' => $data['text'] ?? null,
            'yourCode' => $isCoding ? ($data['code_source'] ?? '') : null,
            'yourLanguage' => $isCoding ? ($data['language'] ?? null) : null,
            // SỬA 3/9 (khách hỏi "Chưa đúng là sao" — không phân biệt được sai đáp án với lỗi
            // biên dịch/quá giờ) — thêm nhãn tiếng Việt CỤ THỂ (VerdictStatus::label()) + chi
            // tiết lỗi biên dịch/runtime của test case ĐẦU TIÊN không Accepted, để học sinh tự
            // sửa được thay vì chỉ thấy "✕ Chưa đúng" chung chung — xem judgeCodingAnswer().
            'codingVerdict' => $codingResult['verdict'] ?? null,
            'codingVerdictLabel' => $codingResult['verdictLabel'] ?? null,
            'codingFailureDetail' => $codingResult['failureDetail'] ?? null,
            'compositeParts' => $compositeResult['parts'] ?? null,
        ];

        Session::put(self::SESSION_KEY, $state);

        return true;
    }

    /**
     * SỬA 31/8 (2) — chấm TỪNG PHẦN của câu Composite (mỗi phần 1 response_type riêng — xem
     * gói ZIP mẫu "NGU_VAN8DOC_HIEU_001": phần a trắc nghiệm, b đúng/sai, c trả lời ngắn, d tự
     * luận). $rawParts là mảng ['<code phần>' => '<câu trả lời thô>'] gửi lên từ form (xem
     * blade student/practice/by-question-play.blade.php, input name="parts[<code>]").
     *
     * 'essay' (và bất kỳ response_type lạ nào chưa hỗ trợ) CHỈ ghi nhận, KHÔNG tự chấm — chưa
     * có chấm tay/chấm AI cho tự luận, cùng cách xử lý với câu Lập trình toàn hệ thống.
     *
     * @return array{parts: array<int, array>, hasUngraded: bool, allGradableCorrect: bool}
     */
    private function gradeCompositeParts(Question $question, array $rawParts): array
    {
        $parts = $question->grading_config['parts'] ?? [];
        $results = [];
        $hasUngraded = false;
        $allGradableCorrect = true;

        foreach ($parts as $part) {
            $code = $part['code'] ?? '';
            $responseType = $part['response_type'] ?? '';
            $yourAnswer = $rawParts[$code] ?? null;

            $result = [
                'code' => $code,
                'responseType' => $responseType,
                'points' => $part['points'] ?? 0,
                'yourAnswer' => $yourAnswer,
            ];

            switch ($responseType) {
                case 'single_choice':
                    $correct = $part['correct_answer'] ?? null;
                    $isPartCorrect = QuestionGrader::isChoiceCorrect($yourAnswer, $correct);
                    $result += ['gradable' => true, 'isCorrect' => $isPartCorrect, 'correctAnswer' => $correct];
                    $allGradableCorrect = $allGradableCorrect && $isPartCorrect;
                    break;
                case 'true_false':
                    $correct = (bool) ($part['correct_answer'] ?? false);
                    $isPartCorrect = QuestionGrader::isTrueFalseCorrect($yourAnswer, $correct);
                    $result += ['gradable' => true, 'isCorrect' => $isPartCorrect, 'correctAnswer' => $correct ? 'true' : 'false'];
                    $allGradableCorrect = $allGradableCorrect && $isPartCorrect;
                    break;
                case 'short_answer':
                    $accepted = $part['accepted_answers'] ?? [];
                    $isPartCorrect = QuestionGrader::matchesAcceptedAnswers((string) ($yourAnswer ?? ''), $accepted, $part['normalization'] ?? []);
                    $result += ['gradable' => true, 'isCorrect' => $isPartCorrect, 'correctAnswer' => implode(', ', $accepted)];
                    $allGradableCorrect = $allGradableCorrect && $isPartCorrect;
                    break;
                default: // 'essay' hoặc dạng lạ chưa hỗ trợ
                    $hasUngraded = true;
                    $result += ['gradable' => false, 'isCorrect' => null, 'correctAnswer' => null];
                    break;
            }

            $results[] = $result;
        }

        return ['parts' => $results, 'hasUngraded' => $hasUngraded, 'allGradableCorrect' => $allGradableCorrect];
    }

    /**
     * SỬA (nối máy chấm Judge0 thật) — chấm NGAY câu Lập trình đang luyện bằng Judge0 (đồng
     * bộ, test case đọc từ Question::grading_config['test_cases'] — mảng {input,output}
     * phẳng, mọi test case đều tính, không phân biệt sample/hidden). null nếu học sinh chưa
     * gõ code nào, hoặc nếu Judge0 không gọi được (mất mạng/đường hầm SSH đứt) — 2 trường hợp
     * này answer() coi là "chưa chấm được", KHÔNG phải "sai".
     *
     * SỬA 3/9 — trả thêm 'verdictLabel' (VerdictStatus::label(), tiếng Việt) và
     * 'failureDetail' (compile_output/stderr của test case ĐẦU TIÊN không Accepted, nếu có)
     * để học sinh/giáo viên tự chẩn đoán được "Chưa đúng" là do sai kết quả, lỗi biên dịch,
     * hay quá thời gian — thay vì chỉ 1 dòng "✕ Chưa đúng" chung chung như trước.
     *
     * @return array{isAccepted: bool, verdict: string, verdictLabel: string, failureDetail: ?string}|null
     */
    private function judgeCodingAnswer(Question $question, array $data): ?array
    {
        $codeSource = (string) ($data['code_source'] ?? '');

        if (trim($codeSource) === '') {
            return null;
        }

        $config = $question->grading_config ?? [];
        $testCases = collect($config['test_cases'] ?? [])
            ->map(fn ($tc) => ['input' => (string) ($tc['input'] ?? ''), 'expected_output' => (string) ($tc['output'] ?? '')])
            ->all();
        $timeLimitMs = (int) ($config['time_limit_ms'] ?? 5000);
        $memoryLimitKb = (int) ($config['memory_limit_mb'] ?? 256) * 1024;

        try {
            $result = $this->codeJudging->judge($codeSource, $data['language'] ?? null, $testCases, $timeLimitMs, $memoryLimitKb);
        } catch (Throwable $e) {
            Log::warning('Không chấm được câu luyện tập Lập trình #'.$question->id.' (Judge0 không tới được)', ['exception' => $e]);

            return null;
        }

        $failureDetail = null;
        if (! $result['isAccepted']) {
            foreach ($result['details'] as $detail) {
                if ($detail['status'] !== 'Accepted') {
                    $failureDetail = trim(($detail['compileOutput'] ?? '').("\n".($detail['stderr'] ?? '')));
                    $failureDetail = $failureDetail !== '' ? $failureDetail : null;
                    break;
                }
            }
        }

        return [
            'isAccepted' => $result['isAccepted'],
            'verdict' => $result['verdict']->value,
            'verdictLabel' => $result['verdict']->label(),
            'failureDetail' => $failureDetail,
        ];
    }

    /** student.practiceByQuestion.next — qua câu kế tiếp, xoá feedback câu vừa xong. */
    public function advance(): void
    {
        $state = Session::get(self::SESSION_KEY);

        if (! is_array($state)) {
            return;
        }

        $state['index']++;
        $state['feedback'] = null;

        Session::put(self::SESSION_KEY, $state);
    }

    public function stop(): ?string
    {
        $state = Session::get(self::SESSION_KEY);
        $returnUrl = is_array($state) ? ($state['returnUrl'] ?? null) : null;

        Session::forget(self::SESSION_KEY);

        return $returnUrl;
    }
}
