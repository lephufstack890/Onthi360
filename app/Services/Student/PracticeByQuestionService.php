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

class PracticeByQuestionService
{
    private const SESSION_KEY = 'practice_by_question';

    public function __construct(
        private readonly QuestionRepositoryInterface $questions,
        private readonly TagRepositoryInterface $tags,
        private readonly CodeJudgingService $codeJudging,
    ) {}

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
            'codingVerdict' => $codingResult['verdict'] ?? null,
            'codingVerdictLabel' => $codingResult['verdictLabel'] ?? null,
            'codingTestCases' => $codingResult['testCases'] ?? null,
            'compositeParts' => $compositeResult['parts'] ?? null,
        ];

        Session::put(self::SESSION_KEY, $state);

        return true;
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
     * @return array{isAccepted
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

        return [
            'isAccepted' => $result['isAccepted'],
            'verdict' => $result['verdict']->value,
            'verdictLabel' => $result['verdict']->label(),
            'testCases' => $result['details'],
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
