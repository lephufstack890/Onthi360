<?php

namespace App\Services\Admin;

use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;

/**
 * Gom truy vấn cho admin.featured-teachers.index — PUB-10 (trang vinh danh, 12.2).
 *
 * TODO: chưa có trường "featured" trong schema (teacher_profiles) — cần migration thêm cột
 * is_featured (hoặc bảng riêng) trước khi nút "Vinh danh/Bỏ vinh danh" có tác dụng thật.
 */
class FeaturedTeacherService
{
    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private TeacherProfileRepositoryInterface $teacherProfiles,
    ) {}

    /** @return array{tabs: array, teachers: array} */
    public function indexData(): array
    {
        $tabs = [
            ['label' => 'Cuộc thi', 'href' => route('admin.competitions.index'), 'active' => false, 'count' => $this->competitions->count()],
            ['label' => 'Giáo viên tiêu biểu', 'href' => route('admin.featured-teachers.index'), 'active' => true, 'count' => $this->teacherProfiles->countApproved()],
        ];

        $teachers = $this->teacherProfiles->approvedWithUser(50)->map(fn ($p) => [
            'id' => $p->user_id,
            'name' => $p->user->name ?? '',
            'subject' => is_array($p->subjects) && count($p->subjects) > 0 ? $p->subjects[0] : '',
            'featured' => false,
        ])->all();

        return ['tabs' => $tabs, 'teachers' => $teachers];
    }
}
