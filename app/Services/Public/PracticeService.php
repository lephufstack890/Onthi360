<?php

namespace App\Services\Public;

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
        $items = $this->assessments->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->withCount('items')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'itemsCount' => $a->items_count,
                'totalPoints' => $a->total_points,
                'durationMinutes' => $a->duration_minutes,
            ])->all();

        return [
            'items' => $items,
            // Chỉ học sinh đã đăng nhập mới vào thẳng student.assessment.take (STU-04);
            // khách/vai trò khác vẫn thấy đề nhưng phải đăng nhập trước (4.1: "đăng nhập để
            // bắt đầu/nộp").
            'canTakeDirectly' => $viewer !== null && $viewer->hasRole(Role::STUDENT),
        ];
    }
}
