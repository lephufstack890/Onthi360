<?php

namespace App\Services\Student;

use App\Enums\AttemptSource;
use App\Enums\AttemptStatus;
use App\Enums\VerdictStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Services\CodeJudgingService;
use App\Services\QuestionGrader;
use App\Support\PracticeFilters;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Throwable;

class PracticeByQuestionService
{
    private const SESSION_KEY = 'practice_by_question';

    public function __construct(
        private readonly QuestionRepositoryInterface $questions,
        private readonly TagRepositoryInterface $tags,
        private readonly CodeJudgingService $codeJudging,
    ) {}

    /**
     * SỬA 9/9 (6) (khách: "hiển thị cho đầy đủ dạng câu hỏi, với khi lọc dạng câu hỏi thì nó sẽ
     * hiển thị chuyên đề thuộc dạng đó cho đúng").
     *
     * Trước đây chỉ trả về danh sách chuyên đề PHẲNG, không kèm dạng câu — nên màn chọn lọc:
     *   · thiếu hẳn dạng "Lập trình" dù idsForPractice() vẫn luyện được dạng này;
     *   · mời chọn chuyên đề không có câu nào ở dạng đang chọn, bấm vào là báo "không tìm thấy".
     * Giờ trả kèm SỐ CÂU theo từng dạng cho từng chuyên đề, để màn chọn lọc ẩn/hiện đúng và
     * hiện luôn số câu. Đếm bằng đúng điều kiện của idsForPractice() (xem TagRepository).
     */
    public function setupData(): array
    {
        // SỬA 9/9 (7) — dời phần dựng dữ liệu bộ lọc sang App\Support\PracticeFilters để màn
        // công khai (Public\PracticeService) dùng CHUNG, tránh 2 màn lệch logic lọc như trước.
        return PracticeFilters::options($this->tags);
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
     * SỬA 18/9 — thêm $backLabel (tuỳ chọn). Phiên luyện 1 câu giờ mở được từ HAI nơi: "Tài
     * liệu của tôi" (bài tập của sản phẩm) và bảng bài tập ở trang Luyện tập công khai. Hai
     * nơi quay về hai chỗ khác nhau nên nút quay lại không thể ghi cứng một nhãn như trước.
     * Bỏ trống -> giữ nguyên nhãn cũ ("Quay lại Tài liệu của tôi"), không đổi hành vi hiện có.
     */
    public function startForQuestion(int $questionId, ?string $returnUrl = null, ?string $backLabel = null): void
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
            'backLabel' => $backLabel,
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
                // SỬA 19/9 (7) (khách: "nộp bài phần lập trình xong nó không hiện tỉ lệ AC") —
                // màn "đã hoàn tất" trước đây chỉ có một câu chúc mừng, không một con số nào.
                'summary' => $this->finishedSummary($state),
                'mode' => $state['mode'] ?? null,
                'returnUrl' => $state['returnUrl'] ?? null,
                'backLabel' => $state['backLabel'] ?? null,
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
            'backLabel' => $state['backLabel'] ?? null,
        ];
    }

    /**
     * SỬA 19/9 (7) — BẢNG KẾT QUẢ của phiên luyện vừa xong, đọc từ CSDL chứ không từ session.
     *
     * Vì sao đọc từ CSDL: session chỉ đếm được "đúng mấy câu"; điểm thật, verdict thật (Sai kết
     * quả / Quá thời gian / Lỗi biên dịch…) nằm ở attempt_answers do recordSubmission() ghi.
     * Đây cũng đúng là bộ số mà Tỷ lệ AC ngoài trang Luyện tập dùng, nên hai nơi không thể nói
     * khác nhau.
     *
     * Trả null khi phiên chưa ghi được lượt nào (chưa đăng nhập, hoặc mọi câu đều không chấm
     * được) — view sẽ chỉ hiện lời chúc mừng như cũ thay vì một bảng rỗng.
     *
     * @param  array<string, mixed>  $state
     * @return array{rows: array<int, array<string, mixed>>, earned: int, maxPoints: int, scorePercent: int, acceptedCount: int}|null
     */
    private function finishedSummary(array $state): ?array
    {
        $questionIds = array_values($state['question_ids'] ?? []);
        $attemptId = $state['attempt_id'] ?? null;

        if ($questionIds === [] || $attemptId === null) {
            return null;
        }

        $attempt = Attempt::with('answers')->find($attemptId);

        if ($attempt === null || $attempt->answers->isEmpty()) {
            return null;
        }

        $answers = $attempt->answers->keyBy('question_id');
        $questions = Question::query()
            ->whereIn('id', $questionIds)
            ->get(['id', 'code', 'title', 'points', 'type'])
            ->keyBy('id');

        $rows = [];
        $earned = 0;
        $maxPoints = 0;
        $acceptedCount = 0;

        /*
         * Duyệt theo DANH SÁCH CÂU CỦA PHIÊN, không phải theo các câu đã trả lời.
         *
         * Vì sao quan trọng: nếu chỉ cộng điểm của những câu đã làm thì học sinh làm 1/3 câu
         * và câu đó đúng sẽ thấy "100%" — sai hoàn toàn. Mẫu số phải là TỔNG ĐIỂM CẢ PHIÊN,
         * và câu chưa làm vẫn hiện trong bảng với nhãn "Chưa trả lời" để không ai tưởng là
         * mình đã làm hết.
         */
        foreach ($questionIds as $questionId) {
            $question = $questions->get($questionId);
            $answer = $answers->get($questionId);

            $points = (int) ($question?->points ?? 0);
            $score = (int) ($answer?->score ?? 0);
            $accepted = $answer !== null && $answer->verdict === VerdictStatus::Accepted;

            $maxPoints += $points;
            $earned += $score;
            $acceptedCount += $accepted ? 1 : 0;

            /*
             * SỬA 19/9 (8) — TỈ LỆ AC của câu lập trình: số test đã qua / tổng số test, ghi lúc
             * chấm (recordSubmission()). null = bài nộp từ trước khi có 2 cột, hoặc máy chủ
             * chưa migrate, hoặc câu không phải dạng lập trình -> view ẩn hẳn phần tỉ lệ thay
             * vì hiện "0/0 test" gây hiểu nhầm là bài không có test nào.
             */
            $totalTests = $answer?->total_tests !== null ? (int) $answer->total_tests : null;
            $passedTests = $answer?->passed_tests !== null ? (int) $answer->passed_tests : null;

            $rows[] = [
                'code' => $question?->code ?? '—',
                'title' => $question?->title ?? 'Câu hỏi đã bị xoá',
                'isCoding' => ($question?->type?->value ?? null) === 'coding',
                'answered' => $answer !== null,
                'isAccepted' => $accepted,
                'verdictLabel' => $answer === null ? 'Chưa trả lời' : ($answer->verdict?->label() ?? 'Chưa chấm'),
                'score' => $score,
                'points' => $points,
                'passedTests' => $passedTests,
                'totalTests' => $totalTests,
                'testPercent' => ($totalTests !== null && $totalTests > 0 && $passedTests !== null)
                    ? (int) round($passedTests / $totalTests * 100)
                    : null,
            ];
        }

        return [
            'rows' => $rows,
            'earned' => $earned,
            'maxPoints' => $maxPoints,
            // Thang điểm chưa đặt (points = 0 hết) thì không chia được -> 0% thay vì lỗi chia 0.
            'scorePercent' => $maxPoints > 0 ? (int) round($earned / $maxPoints * 100) : 0,
            'acceptedCount' => $acceptedCount,
        ];
    }

    /**
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

        // SỬA 18/9 — judgeCodingAnswer() giờ trả ['error' => ...] thay vì null khi KHÔNG chấm
        // được (chưa viết mã / bài thiếu test / máy chấm chết). 'gradable' = false y như trước
        // nên KHÔNG bị tính là sai, chỉ thêm việc nói rõ lý do ra màn hình.
        $codingError = $isCoding ? ($codingResult['error'] ?? null) : null;
        $gradable = ($isCoding ? $codingError === null : true) && ! ($isComposite && $compositeResult['hasUngraded']);

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
            'codingVerdict' => $codingResult['verdict'] ?? null,
            'codingVerdictLabel' => $codingResult['verdictLabel'] ?? null,
            'codingTestCases' => $codingResult['testCases'] ?? null,
            'codingError' => $codingError,
            'codingCompileError' => $codingResult['compileError'] ?? null,
            'codingErrorLine' => $codingResult['errorLine'] ?? null,
            'codingErrorMessage' => $codingResult['errorMessage'] ?? null,
            'compositeParts' => $compositeResult['parts'] ?? null,
        ];

        // SỬA 18/9 — GHI NHẬN vào attempts/attempt_answers. Phải làm sau khi đã có
        // $isCorrect/$gradable, và trước khi trả về, để Tỷ lệ AC + nhãn "Luyện lại" ngoài trang
        // Luyện tập cập nhật ngay lần tải kế tiếp. Xem recordSubmission().
        $this->recordSubmission($question, $state, $data, $isCorrect, $gradable, $codingResult);

        Session::put(self::SESSION_KEY, $state);

        return true;
    }

    /**
     * SỬA 18/9 (khách: "làm bài rồi mà không hiển % tỉ lệ, không chuyển chữ Làm bài thành
     * Luyện lại") — LƯU bài làm xuống CSDL.
     *
     * Trước đây màn này cố ý chỉ giữ trong session, nên hai chỗ đọc attempt_answers là
     * Public\PracticeService::problemRows() (Tỷ lệ AC + trạng thái ac/doing) và tab "Lịch sử
     * làm bài" đều không thấy gì — học sinh làm bao nhiêu bài thì ngoài kia vẫn 0%.
     *
     * MỖI PHIÊN luyện = 1 Attempt (assessment_id = null: không thuộc đề nào, xem migration
     * make_assessment_id_nullable_on_attempts_table); MỖI CÂU trong phiên = 1 AttemptAnswer,
     * nộp lại cùng câu thì CẬP NHẬT dòng cũ và tăng submission_count chứ không đẻ thêm dòng —
     * đúng cách AttemptService đang làm cho bài thi, để Tỷ lệ AC không bị thổi phồng chỉ vì
     * một người bấm nộp nhiều lần.
     *
     * Chỉ ghi khi CHẤM ĐƯỢC ($gradable): máy chấm chết hay bài thiếu test thì không có kết quả
     * thật nào để tính, ghi vào chỉ làm bẩn số liệu.
     *
     * @param  array<string, mixed>  $state  tham chiếu — nhận thêm khoá 'attempt_id' của phiên.
     */
    private function recordSubmission(Question $question, array &$state, array $data, bool $isCorrect, bool $gradable, ?array $codingResult): void
    {
        $user = Auth::user();

        if ($user === null || ! $gradable) {
            return;
        }

        $attempt = isset($state['attempt_id']) ? Attempt::find($state['attempt_id']) : null;

        if ($attempt === null) {
            $attempt = Attempt::create([
                'user_id' => $user->id,
                'assessment_id' => null,
                'source' => AttemptSource::Personal->value,
                'started_at' => now(),
                'status' => AttemptStatus::Graded->value,
                'total_score' => 0,
                'is_provisional' => false,
            ]);

            $state['attempt_id'] = $attempt->id;
        }

        $score = $isCorrect ? (int) $question->points : 0;

        $verdict = match (true) {
            $question->type->value === 'coding' => $codingResult['verdict'] ?? VerdictStatus::SystemError->value,
            $isCorrect => VerdictStatus::Accepted->value,
            default => VerdictStatus::WrongAnswer->value,
        };

        $existing = $attempt->answers()->where('question_id', $question->id)->first();

        $payload = [
            // 'answer' là cột JSON — gom cả 3 dạng trả lời vào đây, bỏ khoá rỗng cho gọn.
            'answer' => array_filter([
                'selected_option' => $data['selected_option'] ?? null,
                'text' => $data['text'] ?? null,
                'parts' => $data['parts'] ?? null,
            ], fn ($v) => $v !== null && $v !== []),
            'code_source' => $data['code_source'] ?? null,
            'language' => $data['language'] ?? null,
            'verdict' => $verdict,
            'score' => $score,
            'graded_at' => now(),
            'submission_count' => ($existing?->submission_count ?? 0) + 1,
        ];

        /*
         * SỬA 19/9 (8) — ĐẾM số test đã qua cho câu Lập trình. Máy chấm vốn trả chi tiết từng
         * test ($codingResult['testCases']), trước giờ chỉ dùng để vẽ ra màn rồi bỏ; giữ lại 2
         * con số này thì sau khi thoát bài, màn tổng kết mới nói được "Đúng 4/20 test · 20%".
         *
         * KHÔNG lưu cả mảng chi tiết: trong đó có dữ liệu vào + đáp án đúng của từng test.
         */
        if (AttemptAnswer::supportsTestCounts()) {
            $details = $codingResult['testCases'] ?? null;

            $payload['total_tests'] = is_array($details) ? count($details) : null;
            $payload['passed_tests'] = is_array($details)
                ? count(array_filter($details, fn ($d) => ($d['isAccepted'] ?? false) === true))
                : null;
        }

        $attempt->answers()->updateOrCreate(['question_id' => $question->id], $payload);

        // Điểm của phiên = tổng điểm các câu đã chấm trong phiên. submitted_at đặt mỗi lần nộp
        // để lượt này nổi lên đầu tab "Lịch sử làm bài" (truy vấn ở đó lọc whereNotNull).
        $attempt->update([
            'total_score' => (int) $attempt->answers()->sum('score'),
            'submitted_at' => now(),
        ]);
    }

    /**
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
     * Chấm 1 bài Lập trình. Trả ['error' => '<lý do>'] khi KHÔNG chấm được (chưa viết mã, bài
     * chưa có test case, hoặc máy chấm không tới được) — nơi gọi coi đó là "chưa chấm", KHÔNG
     * phải "làm sai", và hiện nguyên lý do ra cho học sinh đọc.
     *
     * @return array{isAccepted?: bool, verdict?: string, verdictLabel?: string, testCases?: array, error?: string}
     */
    private function judgeCodingAnswer(Question $question, array $data): ?array
    {
        $codeSource = (string) ($data['code_source'] ?? '');

        if (trim($codeSource) === '') {
            return ['error' => 'Bạn chưa viết mã nguồn nào — chưa có gì để chấm.'];
        }

        $config = $question->grading_config ?? [];
        $testCases = collect($config['test_cases'] ?? [])
            ->map(fn ($tc) => ['input' => (string) ($tc['input'] ?? ''), 'expected_output' => (string) ($tc['output'] ?? '')])
            ->all();
        $timeLimitMs = (int) ($config['time_limit_ms'] ?? 5000);
        $memoryLimitKb = (int) ($config['memory_limit_mb'] ?? 256) * 1024;

        // SỬA 18/9 (khách: "ghi nhận bài làm máy chấm vẫn không chấm được") — CÂU HỎI KHÔNG CÓ
        // TEST CASE là một nguyên nhân KHÁC HẲN việc máy chấm chết, nhưng trước đây cả hai đều
        // rơi vào cùng một kết cục im lặng. Nói thẳng ra để người soạn đề biết đường bổ sung
        // test, thay vì học sinh và giáo viên cùng ngồi đoán.
        if ($testCases === []) {
            return ['error' => 'Bài này chưa có test case nào để chấm — báo giáo viên bổ sung test cho bài.'];
        }

        try {
            $result = $this->codeJudging->judge($codeSource, $data['language'] ?? null, $testCases, $timeLimitMs, $memoryLimitKb, $config['file_io'] ?? null);
        } catch (Throwable $e) {
            Log::warning('Không chấm được câu luyện tập Lập trình #'.$question->id.' (Judge0 không tới được)', ['exception' => $e]);

            // SỬA 18/9 — trước đây trả null, feedback im lặng hiện "Chưa đúng" nên học sinh
            // tưởng mình sai trong khi thật ra máy chấm không chạy. Trả kèm lý do để view hiện
            // đúng bản chất (xem exercise-play.blade.php / by-question-play.blade.php).
            return ['error' => 'Máy chấm chưa kết nối được nên chưa chấm được bài — bài làm của bạn KHÔNG bị tính là sai. Báo giáo viên/quản trị viên kiểm tra máy chấm giúp bạn.'];
        }

        return [
            'isAccepted' => $result['isAccepted'],
            'verdict' => $result['verdict']->value,
            'verdictLabel' => $result['verdict']->label(),
            'testCases' => $result['details'],
            // SỬA 23/9 — mã không biên dịch được thì CodeJudgingService dừng ngay sau test đầu
            // và trả kèm số dòng sai; đẩy tiếp ra view để hiện "Lỗi ở dòng N".
            'compileError' => $result['compileError'] ?? null,
            'errorLine' => $result['errorLine'] ?? null,
            'errorMessage' => $result['errorMessage'] ?? null,
        ];
    }

    /**
     * student.practiceByQuestion.run (SỬA 18/9, khách: "chỗ chạy test không được") — CHẠY THỬ
     * mã của học sinh với dữ liệu vào tự gõ ở ô Input, trả stdout cho ô Output.
     *
     * KHÔNG chấm điểm, KHÔNG đụng gì tới tiến trình phiên luyện (không tăng 'answered', không
     * ghi 'feedback') — bấm chạy thử 20 lần cũng không ảnh hưởng kết quả. Câu hỏi lấy từ ĐÚNG
     * phiên đang mở trong session chứ không nhận id từ client: người dùng không thể mượn màn
     * này để chạy mã trên máy chấm cho một câu mà họ không được mở.
     *
     * @param  array{code_source?: string, language?: string, stdin?: string}  $data
     * @return array{ok: bool, message?: string, ranCleanly?: bool, statusLabel?: string, output?: string, stderr?: ?string, compileOutput?: ?string, time?: ?string, memory?: ?int}
     *                'ok' ở đây nghĩa là CÓ KẾT QUẢ ĐỂ HIỆN (đã gọi được máy chấm) — khác
     *                'ranCleanly' của CodeJudgingService::run() (chương trình chạy sạch hay không).
     */
    public function runOnce(array $data): array
    {
        $state = Session::get(self::SESSION_KEY);

        if (! is_array($state) || empty($state['question_ids']) || $state['index'] >= count($state['question_ids'])) {
            return ['ok' => false, 'message' => 'Phiên luyện tập đã kết thúc — mở lại bài để chạy thử.'];
        }

        $question = Question::find($state['question_ids'][$state['index']]);

        if ($question === null || $question->type->value !== 'coding') {
            return ['ok' => false, 'message' => 'Chỉ bài Lập trình mới chạy thử được.'];
        }

        // Kiểm 2 điều kiện này TRƯỚC khi gọi máy chấm để báo đúng lý do — gọi rồi mới bắt
        // ngoại lệ thì mọi lỗi đều ra chung một câu "không kết nối được máy chấm", sai sự thật.
        if (trim((string) ($data['code_source'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Chưa có mã nguồn để chạy — viết code rồi bấm lại.'];
        }

        if (\App\Services\CodeJudgingService::languageId($data['language'] ?? null) === null) {
            return ['ok' => false, 'message' => 'Ngôn ngữ này chưa chạy được trên máy chấm.'];
        }

        $config = $question->grading_config ?? [];

        try {
            $result = $this->codeJudging->run(
                (string) ($data['code_source'] ?? ''),
                $data['language'] ?? null,
                (string) ($data['stdin'] ?? ''),
                (int) ($config['time_limit_ms'] ?? 5000),
                (int) ($config['memory_limit_mb'] ?? 256) * 1024,
                $config['file_io'] ?? null,
            );
        } catch (Throwable $e) {
            // Đứt đường hầm SSH/máy chấm chưa bật/sai token đều rơi vào đây. Nói THẲNG lý do
            // cho học sinh thay vì im lặng — đây đúng là tình huống khách đang gặp.
            Log::warning('Chạy thử thất bại ở câu #'.$question->id.' (Judge0 không tới được)', ['exception' => $e]);

            return ['ok' => false, 'message' => 'Không kết nối được máy chấm — báo giáo viên/quản trị viên kiểm tra máy chấm giúp bạn.'];
        }

        return ['ok' => true] + $result;
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
     * SỬA 19/9 (8) (khách: "nộp bài xong thoát bài tập thì phải thấy tỉ lệ AC chứ đây không
     * thấy luôn") — bấm "Thoát bài tập" KHÔNG còn vứt sạch kết quả.
     *
     * Hành vi cũ: xoá session rồi đá thẳng về trang trước. Học sinh vừa nộp bài xong, bấm
     * thoát là mất luôn mọi thứ vừa chấm — muốn xem kết quả thì phải nhớ bấm đúng nút "Hoàn
     * tất bài tập" nằm tít cuối khối kết quả, mà nút đó lại hay bị khuất.
     *
     * Hành vi mới: ĐÃ trả lời ít nhất một câu thì đẩy phiên về trạng thái "đã xong" và GIỮ
     * session để màn tổng kết (có tỉ lệ AC, điểm, kết quả từng câu) hiện ra; CHƯA làm gì thì
     * thoát thẳng như cũ — bắt người chưa làm gì phải xem một bảng rỗng là vô nghĩa.
     *
     * @return array{finish: bool, returnUrl: string|null} finish=true -> nơi gọi đưa về màn chơi
     *                                                     để hiện tổng kết; false -> rời đi luôn.
     */
    public function stop(): array
    {
        $state = Session::get(self::SESSION_KEY);
        $returnUrl = is_array($state) ? ($state['returnUrl'] ?? null) : null;

        /*
         * ĐÃ ở màn tổng kết rồi (index đã ra ngoài danh sách câu) mà bấm "Thoát bài tập" lần
         * nữa thì phải RỜI ĐI THẬT. Thiếu điều kiện này là bẫy chuột: bấm thoát lại quay về
         * đúng màn tổng kết, bấm mấy lần cũng không ra được.
         */
        $alreadyFinished = is_array($state)
            && ! empty($state['question_ids'])
            && (int) ($state['index'] ?? 0) >= count($state['question_ids']);

        $hasWork = ! $alreadyFinished
            && is_array($state)
            && (int) ($state['answered'] ?? 0) > 0
            && ! empty($state['question_ids']);

        if ($hasWork) {
            // Đặt con trỏ ra ngoài danh sách câu = dấu hiệu "đã xong" mà playData() vẫn dùng.
            $state['index'] = count($state['question_ids']);
            $state['feedback'] = null;
            Session::put(self::SESSION_KEY, $state);

            return ['finish' => true, 'returnUrl' => $returnUrl];
        }

        Session::forget(self::SESSION_KEY);

        return ['finish' => false, 'returnUrl' => $returnUrl];
    }
}
