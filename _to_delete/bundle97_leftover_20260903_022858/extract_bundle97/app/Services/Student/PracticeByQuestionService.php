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
                // SỬA 31/8 — màn "đã xong" đổi nút quay lại khi đây là phiên "Làm bài" 1 câu
                // (xem startForQuestion()) — quay về đúng trang sản phẩm thay vì trang "Luyện
                // tập theo câu" chung.
                'mode' => $state['mode'] ?? null,
                'returnUrl' => $state['returnUrl'] ?? null,
            ];
        }

        $question = Question::with('tags')->find($state['question_ids'][$index]);

        // Câu hỏi có thể đã bị Admin/Giáo viên lưu trữ/xoá SAU khi phiên luyện đã xáo trộn
        // xong (6.2 không cấm lưu trữ câu đang có trong 1 phiên luyện đang chạy của học sinh
        // khác) — bỏ qua, coi như đã "trả lời" (không tính đúng/sai) rồi qua câu kế tiếp thay
        // vì làm hỏng cả phiên luyện.
        if ($question === null) {
            $this->advance();

            return $this->playData();
        }

        return [
            'finished' => false,
            'question' => $question,
            'options' => $question->grading_config['options'] ?? [],
            // SỬA 31/8 (2, "mở rộng ZIP bài tập" nhiều dạng câu) — câu Composite (nhiều phần
            // khác dạng) cần thêm danh sách phần con để dựng form — SANITIZED (bỏ đáp án
            // đúng/accepted_answers/rubric), KHÔNG đưa nguyên grading_config['parts'] ra view vì
            // đó có cả đáp án đúng, lộ đáp án trước khi học sinh trả lời.
            'compositeParts' => $question->type->value === 'composite'
                ? $this->sanitizedCompositeParts($question->grading_config['parts'] ?? [])
                : [],
            // SỬA 31/8 (2) — audio/ảnh... đính kèm câu hỏi (bất kỳ dạng nào, không riêng
            // Composite — vd câu trắc nghiệm nghe-hiểu "ANH7AUDIO_DEMO_001" là single_choice
            // thường) — url phục vụ qua route riêng (asset id, có kiểm tra quyền lại ở
            // controller), KHÔNG lộ đường dẫn disk thật.
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

    /**
     * student.practiceByQuestion.answer — chấm câu ĐANG ĐỨNG (theo session['index']), ghi kết
     * quả vào 'feedback' để màn play hiện đáp án đúng/sai trước khi qua câu tiếp theo. false
     * nếu không có phiên đang mở (controller đưa về setup).
     * SỬA (nối máy chấm Judge0 thật) — câu Lập trình giờ CHẤM NGAY qua judgeCodingAnswer()
     * (đồng bộ, học sinh chờ kết quả luôn trong request này, đúng tinh thần "luyện tập xem đáp
     * án ngay" của màn hình này) — 'gradable' chỉ còn false trong trường hợp Judge0 tạm thời
     * không gọi được (mất mạng/đường hầm đứt), khi đó hiện thông báo "đã ghi nhận" trung lập
     * như hành vi cũ, KHÔNG báo sai thành sai/đúng.
     */
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

        // SỬA 31/8 (2, "mở rộng ZIP bài tập" nhiều dạng câu) — thêm nhánh 'composite': chấm
        // TỪNG PHẦN qua gradeCompositeParts() (mỗi phần 1 response_type khác nhau), gán luôn
        // vào biến $compositeResult để dùng lại bên dưới (tránh gọi lại 2 lần) — cú pháp gán
        // trong biểu thức match() ($compositeResult = ...)[...] hợp lệ vì PHP đánh giá gán
        // trước rồi mới lấy phần tử mảng.
        $compositeResult = null;
        $isCorrect = match ($question->type->value) {
            'mcq' => QuestionGrader::isMcqCorrect($question, $data['selected_option'] ?? null),
            'fill_blank' => QuestionGrader::isFillBlankCorrect($question, (string) ($data['text'] ?? '')),
            'composite' => ($compositeResult = $this->gradeCompositeParts($question, $data['parts'] ?? []))['allGradableCorrect'],
            'coding' => $codingResult['isAccepted'] ?? false,
            default => false,
        };

        // SỬA 31/8 (2) — câu Composite có ÍT NHẤT 1 phần "essay" (tự luận, chưa có chấm tay/AI)
        // thì CẢ CÂU coi là "chưa chấm được hoàn toàn". SỬA (nối Judge0) — câu Lập trình giờ
        // "gradable" ĐÚNG khi Judge0 chấm xong được ($codingResult !== null); chỉ false khi
        // Judge0 không gọi được (tránh báo sai thành sai/đúng dù thật ra chưa chấm được).
        $gradable = ($isCoding ? $codingResult !== null : true) && ! ($isComposite && $compositeResult['hasUngraded']);

        // Trả lời lại cùng 1 câu (bấm lại nút "Kiểm tra") không cộng dồn thêm lượt — chỉ tính
        // lần trả lời ĐẦU TIÊN của câu đó trong phiên (feedback === null nghĩa là chưa từng
        // trả lời câu này).
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
            // SỬA (nối Judge0) — nhãn verdict thật (VD "Wrong Answer", "Time Limit Exceeded")
            // để sau này view có thể hiện chi tiết hơn "đúng/sai" đơn thuần, nếu cần.
            'codingVerdict' => $codingResult['verdict'] ?? null,
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
     * @return array{isAccepted: bool, verdict: string}|null
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

        return ['isAccepted' => $result['isAccepted'], 'verdict' => $result['verdict']->value];
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

    /**
     * student.practiceByQuestion.stop — kết thúc phiên luyện, dọn session. SỬA 31/8 — trả về
     * 'returnUrl' đã lưu (nếu phiên "Làm bài" 1 câu, xem startForQuestion()) để controller
     * chuyển đúng về trang sản phẩm; null nếu là phiên luyện tập thường (controller giữ hành vi
     * cũ, về trang "Luyện tập").
     */
    public function stop(): ?string
    {
        $state = Session::get(self::SESSION_KEY);
        $returnUrl = is_array($state) ? ($state['returnUrl'] ?? null) : null;

        Session::forget(self::SESSION_KEY);

        return $returnUrl;
    }
}
