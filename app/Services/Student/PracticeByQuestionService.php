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
            'progress' => ['current' => $index + 1, 'total' => $total, 'correct' => $state['correct'], 'answered' => $state['answered']],
            'feedback' => $state['feedback'],
            'mode' => $state['mode'] ?? null,
            'returnUrl' => $state['returnUrl'] ?? null,
        ];
    }

    /**
     * student.practiceByQuestion.answer — chấm câu ĐANG ĐỨNG (theo session['index']), ghi kết
     * quả vào 'feedback' để màn play hiện đáp án đúng/sai trước khi qua câu tiếp theo. false
     * nếu không có phiên đang mở (controller đưa về setup).
     * SỬA 24/8 (v4) — câu Lập trình KHÔNG tự chấm được (chưa có sandbox) — 'gradable' => false
     * báo cho view biết để hiện thông báo "đã ghi nhận" trung lập thay vì đúng/sai, và KHÔNG
     * cộng vào $state['correct'] (vẫn cộng vào 'answered' như mọi câu khác đã làm).
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

        $isCorrect = match ($question->type->value) {
            'mcq' => QuestionGrader::isMcqCorrect($question, $data['selected_option'] ?? null),
            'fill_blank' => QuestionGrader::isFillBlankCorrect($question, (string) ($data['text'] ?? '')),
            default => false, // 'coding' — chưa có sandbox chấm, xem 'gradable' ở feedback bên dưới
        };

        // Trả lời lại cùng 1 câu (bấm lại nút "Kiểm tra") không cộng dồn thêm lượt — chỉ tính
        // lần trả lời ĐẦU TIÊN của câu đó trong phiên (feedback === null nghĩa là chưa từng
        // trả lời câu này).
        if ($state['feedback'] === null) {
            $state['answered']++;
            if ($isCorrect && ! $isCoding) {
                $state['correct']++;
            }
        }

        $state['feedback'] = [
            'isCorrect' => $isCorrect,
            'gradable' => ! $isCoding,
            'correctOptions' => $question->grading_config['correct_options'] ?? [],
            'acceptedAnswers' => $question->grading_config['accepted_answers'] ?? [],
            'yourSelectedOption' => $data['selected_option'] ?? null,
            'yourText' => $data['text'] ?? null,
            'yourCode' => $isCoding ? ($data['code_source'] ?? '') : null,
            'yourLanguage' => $isCoding ? ($data['language'] ?? null) : null,
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
