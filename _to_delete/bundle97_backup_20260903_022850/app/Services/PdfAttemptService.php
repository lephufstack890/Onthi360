<?php

namespace App\Services;

use App\Enums\AnswerSheetQuestionType;
use App\Enums\AttemptStatus;
use App\Enums\VerdictStatus;
use App\Models\Assessment;
use App\Models\AssessmentAnswerKey;
use App\Models\AssessmentCodingItem;
use App\Models\Attempt;
use App\Models\AttemptAnswerKey;
use App\Models\AttemptCodingItem;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SỬA 19/8 (Giai đoạn 2 — học sinh làm đề PDF, 16/8 mục 1.2/6): logic lưu câu trả lời/chấm
 * điểm/nộp bài cho Attempt của đề content_mode=pdf_answer_sheet — song song với
 * App\Services\AttemptService (đề content_mode=structured), KHÔNG sửa gì ở đó. Vòng đời mở/
 * tiếp tục lượt làm bài (startOrResume/deadlineFor/isExpired/finalizeIfExpired) dùng CHUNG
 * qua AttemptService vì các hàm đó chỉ phụ thuộc Assessment/Attempt ở mức chung, không đụng
 * gì tới Question — xem App\Services\Student\AssessmentService là nơi 2 luồng rẽ nhánh theo
 * Assessment::isPdfMode().
 */
class PdfAttemptService
{
    public function __construct(
        private readonly AttemptRepositoryInterface $attempts,
        private readonly AttemptService $attemptService,
        // SỬA 19/8 (Giai đoạn 5 — "Tự động ghi bảng xếp hạng"): xem CompetitionLeaderboardService
        // — đề đấu Cuộc thi content_mode=pdf_answer_sheet cũng phải được ghi nhận, giống hệt
        // đề content_mode=structured ở AttemptService::submit().
        private readonly CompetitionLeaderboardService $competitionLeaderboard,
    ) {}

    /**
     * Lưu (hoặc cập nhật) toàn bộ câu trả lời gửi lên trong 1 lần autosave — cả đáp án
     * trắc nghiệm/đúng-sai/trả lời ngắn LẪN code của bài lập trình con, nếu có gửi kèm.
     * Chỉ nhận answer_key_id/coding_item_id THẬT SỰ thuộc đúng đề của attempt này — không
     * tin id client gửi lên (16 mục 3).
     *
     * @param  array<int|string, mixed>  $answerKeyInputs  key = answer_key_id
     * @param  array<int|string, array{code_source?:string, language?:string}>  $codingItemInputs  key = coding_item_id
     *
     * @throws ValidationException nếu attempt đã kết thúc, hoặc vừa hết giờ (tự nộp trước khi ném lỗi).
     */
    public function saveDraft(Attempt $attempt, array $answerKeyInputs, array $codingItemInputs): Attempt
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            throw ValidationException::withMessages(['attempt' => 'Lượt làm bài này đã kết thúc, không thể sửa câu trả lời.']);
        }

        if ($this->attemptService->isExpired($attempt)) {
            $this->submit($attempt);

            throw ValidationException::withMessages(['attempt' => 'Đã hết thời gian làm bài — bài của bạn đã được tự động nộp.']);
        }

        /** @var Assessment $assessment */
        $assessment = $attempt->assessment;

        if ($answerKeyInputs !== []) {
            $validAnswerKeys = $assessment->answerKeys()
                ->whereIn('id', array_map('intval', array_keys($answerKeyInputs)))
                ->get()
                ->keyBy('id');

            foreach ($answerKeyInputs as $answerKeyId => $rawAnswer) {
                $answerKey = $validAnswerKeys->get((int) $answerKeyId);
                if ($answerKey === null) {
                    continue; // không thuộc đề này — bỏ qua, không tin id client gửi lên
                }
                $this->saveAnswerKey($attempt, $answerKey, $rawAnswer);
            }
        }

        if ($codingItemInputs !== []) {
            $validCodingItems = $assessment->codingItems()
                ->whereIn('id', array_map('intval', array_keys($codingItemInputs)))
                ->get()
                ->keyBy('id');

            foreach ($codingItemInputs as $codingItemId => $rawInput) {
                $codingItem = $validCodingItems->get((int) $codingItemId);
                if ($codingItem === null) {
                    continue;
                }
                $this->saveCodingItem($attempt, $codingItem, is_array($rawInput) ? $rawInput : []);
            }
        }

        return $attempt;
    }

    /** Chấm ngay bằng AssessmentAnswerKey::isCorrect() — cùng cách MCQ/điền đáp án được chấm ngay ở AttemptService::gradeMcq()/gradeFillBlank(). */
    public function saveAnswerKey(Attempt $attempt, AssessmentAnswerKey $answerKey, mixed $rawAnswer): AttemptAnswerKey
    {
        $rawAnswer = $this->normalizeSubmittedAnswer($answerKey, $rawAnswer);
        $isCorrect = $answerKey->isCorrect($rawAnswer);
        $score = $isCorrect ? $answerKey->points : 0;

        return AttemptAnswerKey::updateOrCreate(
            ['attempt_id' => $attempt->id, 'answer_key_id' => $answerKey->id],
            [
                'submitted_answer' => $rawAnswer,
                'is_correct' => $isCorrect,
                'score' => $score,
                'graded_at' => now(),
            ],
        );
    }

    /**
     * Chuẩn hoá câu trả lời học sinh gửi lên về đúng hình dạng AssessmentAnswerKey::isCorrect()
     * mong đợi — cùng lý do với App\Http\Controllers\Admin\ContentController::
     * normalizeAnswerSheetValue() (dùng khi Admin nhập đáp án ĐÚNG): autosave qua fetch() gửi
     * JSON (true/false thật), nhưng lần nộp cuối cùng đi qua <form> POST thật (biểu mẫu HTML
     * không tự có kiểu bool) nên true_false_group luôn cần ép "1"/"0"/0/1 về bool thật, nếu
     * không phép so sánh !== trong trueFalseGroupMatches() sẽ luôn sai kiểu dù đúng giá trị.
     */
    private function normalizeSubmittedAnswer(AssessmentAnswerKey $answerKey, mixed $raw): mixed
    {
        return match ($answerKey->question_type) {
            AnswerSheetQuestionType::SingleChoice => is_string($raw) || is_numeric($raw) ? strtoupper(trim((string) $raw)) : $raw,
            AnswerSheetQuestionType::ShortAnswer => is_string($raw) || is_numeric($raw) ? trim((string) $raw) : $raw,
            AnswerSheetQuestionType::TrueFalseGroup => is_array($raw)
                ? collect($raw)->mapWithKeys(fn ($v, $k) => [$k => (bool) ((int) $v)])->all()
                : $raw,
        };
    }

    /** Chỉ lưu code — verdict luôn "queued", CHƯA có sandbox chấm code thật (giống giới hạn hiện tại của OJ câu hỏi rời). */
    public function saveCodingItem(Attempt $attempt, AssessmentCodingItem $codingItem, array $rawInput): AttemptCodingItem
    {
        $existing = AttemptCodingItem::where('attempt_id', $attempt->id)
            ->where('coding_item_id', $codingItem->id)
            ->first();

        return AttemptCodingItem::updateOrCreate(
            ['attempt_id' => $attempt->id, 'coding_item_id' => $codingItem->id],
            [
                'code_source' => $rawInput['code_source'] ?? null,
                'language' => $rawInput['language'] ?? null,
                'verdict' => VerdictStatus::Queued->value,
                'submission_count' => ($existing?->submission_count ?? 0) + 1,
            ],
        );
    }

    /**
     * Nộp bài: khoá lượt làm bài, cộng điểm mọi câu đáp án + bài lập trình đã lưu. Bài lập
     * trình luôn "queued" (chưa final) nên tổng điểm vẫn "tạm tính" cho tới khi có chấm thật
     * — cùng khoá lockForUpdate() trong transaction như App\Services\AttemptService::submit()
     * để chặn nộp trùng khi 2 request cùng lúc (double-click/retry mất mạng).
     *
     * @throws ValidationException nếu lượt làm bài đã nộp trước đó.
     */
    public function submit(Attempt $attempt): Attempt
    {
        $locked = DB::transaction(function () use ($attempt) {
            $locked = $this->attempts->query()->whereKey($attempt->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== AttemptStatus::InProgress) {
                throw ValidationException::withMessages(['attempt' => 'Lượt làm bài này đã được nộp trước đó.']);
            }

            $locked->load(['answerKeys', 'codingItems']);

            $answerScore = (int) $locked->answerKeys->sum('score');
            $codingScore = (int) $locked->codingItems->whereNotNull('score')->sum('score');

            $hasPendingCoding = $locked->codingItems->contains(fn (AttemptCodingItem $c) => ! $c->verdict->isFinal());

            $locked->total_score = $answerScore + $codingScore;
            $locked->is_provisional = $hasPendingCoding;
            $locked->submitted_at = now();
            $locked->status = ($hasPendingCoding ? AttemptStatus::Grading : AttemptStatus::Graded)->value;
            $locked->save();

            return $locked;
        });

        // SỬA 19/8 (Giai đoạn 5) — NGOÀI transaction (đã commit), bọc try/catch nuốt lỗi:
        // xem lý do đầy đủ ở App\Services\AttemptService::recordCompetitionLeaderboardSafely()
        // (tác dụng phụ, không được phép làm hỏng việc nộp bài THẬT của học sinh).
        try {
            $this->competitionLeaderboard->recordIfCompetitionExam($locked);
        } catch (Throwable $e) {
            Log::error('Không ghi được bảng xếp hạng Cuộc thi cho attempt PDF #'.$locked->id, ['exception' => $e]);
        }

        return $locked;
    }
}
