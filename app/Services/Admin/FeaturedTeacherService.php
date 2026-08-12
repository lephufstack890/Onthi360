<?php

namespace App\Services\Admin;

use App\Models\TeacherProfile;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;

/**
 * Gom truy vấn + hành động cho admin.featured-teachers.index — PUB-10 (trang vinh danh, 12.2).
 * Chỉ giáo viên Đã được duyệt mới cho vinh danh (App\Models\TeacherProfile::isFeatured()).
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

        $teachers = $this->teacherProfiles->approvedWithUser(50)->map(fn (TeacherProfile $p) => [
            'profile_id' => $p->id,
            'name' => $p->user->name ?? '',
            'subject' => is_array($p->subjects) && count($p->subjects) > 0 ? $p->subjects[0] : '',
            'featured' => $p->is_featured,
            'achievement' => $p->achievement_note ?? '',
        ])->all();

        return ['tabs' => $tabs, 'teachers' => $teachers];
    }

    /** Vinh danh — cho phép kèm ghi chú thành tích hiển thị công khai (12.1 mục 8). */
    public function feature(TeacherProfile $profile, ?string $achievementNote): TeacherProfile
    {
        $profile->update([
            'is_featured' => true,
            'achievement_note' => $achievementNote !== null && $achievementNote !== ''
                ? $achievementNote
                : $profile->achievement_note,
        ]);

        return $profile;
    }

    /** Bỏ vinh danh — không xoá achievement_note, chỉ ẩn khỏi trang công khai. */
    public function unfeature(TeacherProfile $profile): TeacherProfile
    {
        $profile->update(['is_featured' => false]);

        return $profile;
    }
}
