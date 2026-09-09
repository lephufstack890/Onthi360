<?php

namespace App\Services\Public;

use App\Models\AssessmentItem;
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
            // Chỉ học sinh đã đăng nhập mới vào thẳng student.assessment.take (STU-04);
            // khách/vai trò khác vẫn thấy đề nhưng phải đăng nhập trước (4.1: "đăng nhập để
            // bắt đầu/nộp").
            'canTakeDirectly' => $viewer !== null && $viewer->hasRole(Role::STUDENT),
        ], PracticeFilters::options($this->tags));
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
