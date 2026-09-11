<?php

namespace App\Services\Public;

use App\Enums\QuestionType;
use App\Models\AssessmentItem;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Support\PracticeFilters;

/**
 * Luyện tập công khai (PUB-07, 4.1 "Kho bài công khai, lọc, chi tiết đề, đăng nhập để bắt
 * đầu/nộp" + 10.1 "Luyện tập"). Cùng NGUỒN dữ liệu với tab "Tự luyện" của
 * App\Services\Student\PracticeService (type=practice, status=published) để danh sách công
 * khai và danh sách học sinh đã đăng nhập không lệch nhau.
 *
 * SỬA 24/8 — khách chốt: trang này KHÔNG còn ưu tiên "làm theo đề gồm nhiều câu hỏi" nữa, mà
 * ưu tiên lối "Luyện tập theo câu" (chọn dạng câu hỏi + chuyên đề, luyện từng câu, bấm "Câu
 * tiếp theo ›" — cùng cơ chế App\Services\Student\PracticeByQuestionService đã có, xem view
 * public/practice/index.blade.php). Thêm $tags CHỈ để lấy dữ liệu hiển thị bộ lọc (dạng câu
 * + chuyên đề + số câu khả dụng) — KHÔNG đụng gì tới $assessments/indexData() phần
 * "đề" cũ, phần đó vẫn tính nguyên (chỉ bị ẨN ở view) để dễ khôi phục nếu khách đổi ý.
 */
class PracticeService
{
    public function __construct(
        private AssessmentRepositoryInterface $assessments,
        private TagRepositoryInterface $tags,
    ) {}

    /** practice.index — kho bài luyện tập công khai; CTA khác nhau theo việc đã đăng nhập là học sinh hay chưa. */
    public function indexData(?User $viewer): array
    {
        $assessments = $this->assessments->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->withCount('items')
            ->latest()
            ->limit(30)
            ->get();

        // Đề nào có ít nhất 1 câu type=coding — 1 câu truy vấn cho CẢ trang, tránh N+1 (mỗi
        // đề 1 câu). Môn Tin (4.1) có 2 lối chấm khác nhau: trắc nghiệm/điền đáp án chấm tự
        // động ngay, còn code phải qua bộ test/luật riêng — học sinh cần biết trước khi vào
        // làm, không phải bấm vào mới biết đề có code hay không.
        $codingAssessmentIds = $this->assessmentIdsWithCoding($assessments->pluck('id')->all());

        $items = $assessments->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'itemsCount' => $a->items_count,
            'totalPoints' => $a->total_points,
            'durationMinutes' => $a->duration_minutes,
            'hasCoding' => $codingAssessmentIds->contains($a->id),
        ])->all();

        // SỬA 9/9 (7) (khách: "trang luyện tập ngoài public cũng vậy sửa giúp tôi nha") —
        // bộ lọc "Luyện tập theo câu" của trang công khai giờ lấy CHUNG một nguồn với màn học
        // sinh (App\Support\PracticeFilters::options()): đủ 4 dạng câu + số câu của từng
        // chuyên đề TÁCH THEO DẠNG (practiceTypes / practiceTags / practiceTotal).
        // Trước đây chỗ này tự dựng danh sách chuyên đề PHẲNG ($allTags) nên trang công khai
        // lệch hẳn với màn học sinh: thiếu dạng "Câu nhiều phần", và chọn dạng nào cũng đổ ra y
        // một danh sách chuyên đề — chọn "Lập trình" + chuyên đề "Hàm số" là chắc chắn ra 0
        // câu, bấm vào báo "không tìm thấy câu hỏi phù hợp".
        // practiceTotal cũng thay luôn cho practiceQuestionsCount cũ (idsForPractice(null, [])
        // kéo TOÀN BỘ id câu hỏi về chỉ để count()) — cùng điều kiện lọc nên con số không đổi.
        return array_merge([
            'items' => $items,
            // SỬA 11/9 — giao diện mới (education-main/src/components/PracticePage.jsx) có chế độ
            // "Bài tập chuyên đề" liệt kê TỪNG CÂU. Danh sách dưới đây là câu hỏi THẬT trong kho
            // (đã phát hành + công khai + không thuộc sản phẩm riêng), kèm tỷ lệ AC tính từ
            // attempt_answers thật — không có con số minh hoạ nào.
            'problems' => $this->problemRows($viewer),
            // Chỉ học sinh đã đăng nhập mới vào thẳng student.assessment.take (STU-04);
            // khách/vai trò khác vẫn thấy đề nhưng phải đăng nhập trước (4.1: "đăng nhập để
            // bắt đầu/nộp").
            'canTakeDirectly' => $viewer !== null && $viewer->hasRole(Role::STUDENT),
        ], PracticeFilters::options($this->tags));
    }

    /**
     * Danh sách từng câu hỏi cho chế độ "Bài tập chuyên đề".
     *
     * Cùng điều kiện lọc với App\Support\PracticeFilters (đã phát hành, công khai, không
     * gắn vào 1 sản phẩm riêng) nên số câu ở bộ lọc và số dòng trong bảng luôn khớp nhau.
     *
     * Tỷ lệ AC: đếm trên attempt_answers — mỗi bản ghi là 1 lượt của 1 học sinh cho 1 câu;
     * "accepted" = verdict 'accepted' (bài code) hoặc score > 0 (trắc nghiệm/điền đáp án).
     * Gom bằng ĐÚNG 1 câu GROUP BY cho cả trang, không phải mỗi câu 1 truy vấn.
     *
     * @return array<int, array<string, mixed>>
     */
    private function problemRows(?User $viewer): array
    {
        // ĐÚNG cùng điều kiện với QuestionRepository::idsForPractice() (đã phát hành + không
        // thuộc sản phẩm riêng + 4 dạng câu luyện tập). Cố ý KHÔNG thêm lọc visibility ở đây:
        // thêm vào thì số câu ở bộ lọc (PracticeFilters) và số dòng trong bảng sẽ lệch nhau.
        $questions = Question::query()
            ->where('status', 'published')
            ->whereNull('product_id')
            ->whereIn('type', array_keys(PracticeFilters::TYPE_META))
            ->with(['tags:id,name'])
            ->latest()
            ->limit(60)
            ->get();

        if ($questions->isEmpty()) {
            return [];
        }

        $questionIds = $questions->pluck('id')->all();

        $stats = AttemptAnswer::query()
            ->selectRaw('question_id, COUNT(*) as submissions, SUM(CASE WHEN verdict = ? OR score > 0 THEN 1 ELSE 0 END) as accepted', ['accepted'])
            ->whereIn('question_id', $questionIds)
            ->groupBy('question_id')
            ->get()
            ->keyBy('question_id');

        // Số lượt của CHÍNH người đang xem — khách chưa đăng nhập thì là 0, không suy đoán.
        $mine = collect();
        if ($viewer !== null) {
            $mine = AttemptAnswer::query()
                ->selectRaw('question_id, COUNT(*) as mine, SUM(CASE WHEN verdict = ? OR score > 0 THEN 1 ELSE 0 END) as mine_accepted', ['accepted'])
                ->whereIn('question_id', $questionIds)
                ->whereHas('attempt', fn ($q) => $q->where('user_id', $viewer->id))
                ->groupBy('question_id')
                ->get()
                ->keyBy('question_id');
        }

        return $questions->map(function (Question $q) use ($stats, $mine) {
            $row = $stats->get($q->id);
            $submissions = (int) ($row->submissions ?? 0);
            $accepted = (int) ($row->accepted ?? 0);
            $rate = $submissions > 0 ? round($accepted / $submissions * 100, 1) : 0.0;

            $mineRow = $mine->get($q->id);
            $mineCount = (int) ($mineRow->mine ?? 0);
            $mineAccepted = (int) ($mineRow->mine_accepted ?? 0);

            // Trạng thái của người đang xem: đã AC / đang làm dở / chưa nộp.
            $status = 'todo';
            if ($mineAccepted > 0) {
                $status = 'ac';
            } elseif ($mineCount > 0) {
                $status = 'doing';
            }

            $meta = $q->metadata ?? [];
            $difficultyLevel = (int) ($meta['difficulty'] ?? 0);
            if ($difficultyLevel < 1 || $difficultyLevel > 5) {
                // Chưa gắn độ khó thủ công thì suy ra từ điểm của câu (thang 5 sao), vì đây là
                // dữ liệu có thật của câu hỏi chứ không phải con số bịa ra.
                $difficultyLevel = max(1, min(5, (int) ceil(($q->points ?: 10) / 20)));
            }
            $difficultyKey = match (true) {
                $difficultyLevel <= 1 => 'easy',
                $difficultyLevel === 2 => 'easy',
                $difficultyLevel === 3 => 'medium',
                $difficultyLevel === 4 => 'hard',
                default => 'expert',
            };

            $limits = $q->grading_config['limits'] ?? [];

            return [
                'id' => $q->id,
                'code' => $q->code,
                'title' => $q->title,
                'typeKey' => $q->type instanceof QuestionType ? $q->type->value : (string) $q->type,
                'typeLabel' => PracticeFilters::TYPE_META[$q->type instanceof QuestionType ? $q->type->value : (string) $q->type]['label'] ?? 'Câu hỏi',
                'tagIds' => $q->tags->pluck('id')->all(),
                'topicLabel' => $q->tags->first()?->name ?? 'Chưa gắn chuyên đề',
                'difficulty' => $difficultyKey,
                'difficultyLevel' => $difficultyLevel,
                'points' => (int) $q->points,
                'timeLimit' => isset($limits['time_ms']) ? round($limits['time_ms'] / 1000, 1).'s' : '—',
                'memoryLimit' => isset($limits['memory_mb']) ? $limits['memory_mb'].'MB' : '—',
                'submissionCount' => $submissions,
                'acceptedCount' => $accepted,
                'acRate' => $rate,
                'userSubmissions' => $mineCount,
                'status' => $status,
                'subject' => $q->subject,
                'subjectLabel' => $q->subjectLabel(),
                'grade' => $q->grade,
            ];
        })->values()->all();
    }

    /** @param array<int, int> $assessmentIds
     * @return \Illuminate\Support\Collection<int, int> */
    private function assessmentIdsWithCoding(array $assessmentIds): \Illuminate\Support\Collection
    {
        if ($assessmentIds === []) {
            return collect();
        }

        return AssessmentItem::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->whereHas('question', fn ($q) => $q->where('type', 'coding'))
            ->distinct()
            ->pluck('assessment_id');
    }
}
