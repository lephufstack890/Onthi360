<?php

namespace App\Services\Public;

use App\Models\AssessmentItem;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;

/**
 * Luyện tập công khai (PUB-07, 4.1 "Kho bài công khai, lọc, chi tiết đề, đăng nhập để bắt
 * đầu/nộp" + 10.1 "Luyện tập"). Cùng NGUỒN dữ liệu với tab "Tự luyện" của
 * App\Services\Student\PracticeService (type=practice, status=published) để danh sách công
 * khai và danh sách học sinh đã đăng nhập không lệch nhau.
 */
class PracticeService
{
    public function __construct(private AssessmentRepositoryInterface $assessments) {}

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

        return [
            'items' => $items,
            // Chỉ học sinh đã đăng nhập mới vào thẳng student.assessment.take (STU-04);
            // khách/vai trò khác vẫn thấy đề nhưng phải đăng nhập trước (4.1: "đăng nhập để
            // bắt đầu/nộp").
            'canTakeDirectly' => $viewer !== null && $viewer->hasRole(Role::STUDENT),
        ];
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
