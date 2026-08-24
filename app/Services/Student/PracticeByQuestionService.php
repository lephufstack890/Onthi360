<?php

namespace App\Services\Student;

use App\Models\Question;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Services\QuestionGrader;
use Illuminate\Support\Facades\Session;

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
 * Chỉ chọn câu Mcq/FillBlank đã phát hành — xem QuestionRepositoryInterface::idsForPractice().
 * SỬA 24/8 (v3) — khách chốt: dùng CẢ câu hỏi kho riêng giáo viên, không chỉ Kho chung nữa
 * (idsForPractice() đã bỏ điều kiện owner_type='shared') — "đã phát hành" vẫn là điều kiện
 * chặn duy nhất, câu Nháp của giáo viên không lọt ra được. KHÔNG hỗ trợ câu Lập trình vì hệ
 * thống chưa có sandbox chấm code thật (cùng lý do AttemptService chỉ ghi nhận "Queued" cho
 * Lập trình).
 */
class PracticeByQuestionService
{
    private const SESSION_KEY = 'practice_by_question';

    public function __construct(
        private readonly QuestionRepositoryInterface $questions,
        private readonly TagRepositoryInterface $tags,
    ) {}

    /**
     * student.practiceByQuestion.setup — màn chọn tag/dạng câu trước khi bắt đầu.
     * SỬA 24/8 — đổi allOrderedByName() → allWithPracticeQuestions(): chỉ mời chọn chuyên đề
     * THỰC SỰ có câu hỏi thoả idsForPractice() (đã phát hành, Mcq/FillBlank — SỬA 24/8 v3:
     * kể cả câu thuộc kho riêng giáo viên) — chọn 1 tag chỉ gắn cho câu Lập trình hoặc câu
     * chưa phát hành chắc chắn ra 0 câu ở start(), dù tag đó "có dữ liệu" theo nghĩa khác.
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
            'progress' => ['current' => $index + 1, 'total' => $total, 'correct' => $state['correct'], 'answered' => $state['answered']],
            'feedback' => $state['feedback'],
        ];
    }

    /**
     * student.practiceByQuestion.answer — chấm câu ĐANG ĐỨNG (theo session['index']), ghi kết
     * quả vào 'feedback' để màn play hiện đáp án đúng/sai trước khi qua câu tiếp theo. false
     * nếu không có phiên đang mở (controller đưa về setup).
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

        $isCorrect = match ($question->type->value) {
            'mcq' => QuestionGrader::isMcqCorrect($question, $data['selected_option'] ?? null),
            'fill_blank' => QuestionGrader::isFillBlankCorrect($question, (string) ($data['text'] ?? '')),
            default => false,
        };

        // Trả lời lại cùng 1 câu (bấm lại nút "Kiểm tra") không cộng dồn thêm lượt — chỉ tính
        // lần trả lời ĐẦU TIÊN của câu đó trong phiên (feedback === null nghĩa là chưa từng
        // trả lời câu này).
        if ($state['feedback'] === null) {
            $state['answered']++;
            if ($isCorrect) {
                $state['correct']++;
            }
        }

        $state['feedback'] = [
            'isCorrect' => $isCorrect,
            'correctOptions' => $question->grading_config['correct_options'] ?? [],
            'acceptedAnswers' => $question->grading_config['accepted_answers'] ?? [],
            'yourSelectedOption' => $data['selected_option'] ?? null,
            'yourText' => $data['text'] ?? null,
        ];

        Session::put(self::SESSION_KEY, $state);

        return true;
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

    /** student.practiceByQuestion.stop — kết thúc phiên luyện, dọn session. */
    public function stop(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
