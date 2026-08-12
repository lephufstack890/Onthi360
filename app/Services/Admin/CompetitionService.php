<?php

namespace App\Services\Admin;

use App\Enums\CompetitionStatus;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;

/**
 * Gom truy vấn/nhãn cho admin.competitions.index (ADM-05, 11.1: vòng đời cuộc thi).
 */
class CompetitionService
{
    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private TeacherProfileRepositoryInterface $teacherProfiles,
    ) {}

    /** @return array{tabs: array, competitions: array} */
    public function indexData(): array
    {
        $tabs = [
            ['label' => 'Cuộc thi', 'href' => route('admin.competitions.index'), 'active' => true, 'count' => $this->competitions->count()],
            ['label' => 'Giáo viên tiêu biểu', 'href' => route('admin.featured-teachers.index'), 'active' => false, 'count' => $this->teacherProfiles->countApproved()],
        ];

        $competitions = $this->competitions->latest(50)->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->title,
            'type' => $c->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát',
            'time' => ($c->starts_at?->format('d/m') ?? '').' - '.($c->ends_at?->format('d/m/Y') ?? ''),
            'status' => match ($c->status) {
                CompetitionStatus::Upcoming => 'Sắp diễn ra',
                CompetitionStatus::Ongoing => 'Đang diễn ra',
                CompetitionStatus::PendingPublish => 'Chờ công bố',
                CompetitionStatus::Published => 'Đã công bố',
                CompetitionStatus::Archived => 'Lưu trữ',
            },
            'tone' => match ($c->status) {
                CompetitionStatus::Upcoming => 'info',
                CompetitionStatus::Ongoing => 'warning',
                CompetitionStatus::Published => 'success',
                default => 'neutral',
            },
        ])->all();

        return ['tabs' => $tabs, 'competitions' => $competitions];
    }
}
