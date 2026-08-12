<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Models\Question;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\DraftQuestionRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Services\QuestionPublishGuard;

/**
 * Gom truy vấn/nhãn cho admin.content.* (ADM-03, 6.2/6.4/6.5).
 */
class ContentService
{
    public function __construct(
        private MaterialRepositoryInterface $materials,
        private QuestionRepositoryInterface $questions,
        private AssessmentRepositoryInterface $assessments,
        private DraftQuestionRepositoryInterface $draftQuestions,
        private QuestionPublishGuard $publishGuard,
    ) {}

    private function statusLabel(ContentStatus $status): array
    {
        return match ($status) {
            ContentStatus::Draft => ['Nháp', 'neutral'],
            ContentStatus::PendingReview => ['Chờ duyệt', 'warning'],
            ContentStatus::Published => ['Phát hành', 'success'],
            ContentStatus::Archived => ['Lưu trữ', 'neutral'],
        };
    }

    /** @return array{tab: string, tabs: array, rows: array, total: int} */
    public function indexData(string $tab): array
    {
        $counts = [
            'materials' => $this->materials->count(),
            'questions' => $this->questions->countShared(),
            'assessments' => $this->assessments->count(),
            'drafts' => $this->draftQuestions->countPendingReview(),
        ];

        $tabs = [
            ['label' => 'Học liệu (Sách/Chuyên đề/Đề thi)', 'href' => route('admin.content.index'), 'active' => $tab === 'materials', 'count' => $counts['materials']],
            ['label' => 'Kho câu hỏi chung', 'href' => route('admin.content.index', ['tab' => 'questions']), 'active' => $tab === 'questions', 'count' => $counts['questions']],
            ['label' => 'Đề/bộ bài', 'href' => route('admin.content.index', ['tab' => 'assessments']), 'active' => $tab === 'assessments', 'count' => $counts['assessments']],
            ['label' => 'Câu hỏi chờ rà soát (OCR)', 'href' => route('admin.content.index', ['tab' => 'drafts']), 'active' => $tab === 'drafts', 'count' => $counts['drafts']],
        ];

        $rows = [];
        if ($tab === 'questions') {
            $rows = $this->questions->sharedLatestWithOwner(50)->map(function ($q) {
                [$label, $tone] = $this->statusLabel($q->status);

                return ['id' => $q->id, 'title' => $q->title, 'type' => $q->type->value, 'status' => $label, 'tone' => $tone, 'owner' => 'Kho chung'];
            })->all();
        } elseif ($tab === 'assessments') {
            $rows = $this->assessments->latestWithCreator(50)->map(function ($a) {
                [$label, $tone] = $this->statusLabel($a->status);

                return ['id' => $a->id, 'title' => $a->title, 'type' => $a->type->value, 'status' => $label, 'tone' => $tone, 'owner' => $a->owner_type === OwnerType::Shared ? 'Kho chung' : ('GV '.($a->creator->name ?? ''))];
            })->all();
        } elseif ($tab !== 'drafts') {
            $rows = $this->materials->latestWithProduct(50)->map(function ($m) {
                [$label, $tone] = $this->statusLabel($m->status);

                return ['id' => $m->id, 'title' => $m->title, 'type' => $m->type, 'status' => $label, 'tone' => $tone, 'owner' => $m->product?->owner_type === OwnerType::Teacher ? 'Giáo viên' : 'Kho chung'];
            })->all();
        }

        return ['tab' => $tab, 'tabs' => $tabs, 'rows' => $rows, 'total' => $counts[$tab] ?? count($rows)];
    }

    /**
     * admin.content.show dùng chung MỘT route cho cả 3 loại nội dung (Material/Question/
     * Assessment) — route chỉ nhận int $content, không có route-model-binding theo loại vì
     * mỗi tab ở admin.content.index đều trỏ "Xem" vào cùng route với id riêng của loại đó.
     * Ta không thể biết chắc id thuộc loại nào chỉ từ con số (hạn chế thiết kế route có sẵn,
     * không sửa route ở đây) nên tra cứu tuần tự Material -> Question -> Assessment.
     *
     * QuestionPublishGuard::canPublish() chỉ áp dụng cho Question (6.2/6.4 quy định điều kiện
     * phát hành CÂU HỎI). Material và Assessment không đi qua guard này — không có quy tắc
     * publish tương ứng nào được đặc tả cho 2 loại đó, nên $publishErrors luôn rỗng ở 2
     * nhánh đó thay vì ép một Material/Assessment qua guard nhận Question làm tham số.
     *
     * @return array{item: array, publishErrors: array}
     */
    public function showData(int $id): array
    {
        $material = $this->materials->findWithProduct($id);
        if ($material !== null) {
            [$label] = $this->statusLabel($material->status);

            return [
                'item' => ['id' => $material->id, 'title' => $material->title, 'status' => $label],
                'publishErrors' => [],
            ];
        }

        /** @var Question|null $question */
        $question = $this->questions->find($id);
        if ($question !== null) {
            [$label] = $this->statusLabel($question->status);
            $decision = $this->publishGuard->canPublish($question);

            return [
                'item' => ['id' => $question->id, 'title' => $question->title, 'status' => $label],
                'publishErrors' => $decision->allowed ? [] : [$decision->message ?? 'Chưa đủ điều kiện phát hành.'],
            ];
        }

        $assessment = $this->assessments->find($id);
        if ($assessment !== null) {
            [$label] = $this->statusLabel($assessment->status);

            return [
                'item' => ['id' => $assessment->id, 'title' => $assessment->title, 'status' => $label],
                'publishErrors' => [],
            ];
        }

        return [
            'item' => ['id' => $id, 'title' => 'Không tìm thấy nội dung', 'status' => ''],
            'publishErrors' => [],
        ];
    }
}
