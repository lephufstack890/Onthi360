<?php

namespace App\Services\Teacher;

use App\Enums\AccessScope;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;

/** Tổng hợp dữ liệu cho teacher.dashboard (TEA-01). */
class DashboardService
{
    public function __construct(
        private readonly ClassSessionRepositoryInterface $classSessions,
        private readonly AssignmentRepositoryInterface $assignments,
        private readonly AccessRightRepositoryInterface $accessRights,
    ) {}

    public function buildFor(User $user): array
    {
        $classRoomIds = $user->classRoomsTeaching()->pluck('class_rooms.id')->all();

        $upcoming = $this->classSessions->upcomingForClassRoomIds($classRoomIds, 5)
            ->map(fn ($s) => [
                'time' => $s->starts_at->format('d/m H:i'),
                'class' => $s->classRoom->name ?? '',
                'topic' => $s->topic ?? '',
            ])->all();

        $toOpen = $this->assignments->draftOrScheduledForClassRoomIds($classRoomIds, 10)
            ->map(fn ($a) => [
                'title' => $a->assessment->title ?? 'Bài tập',
                'class' => $a->classRoom->name ?? '',
                'chapter' => '', // TODO: chưa có khái niệm "chương" trong schema hiện tại.
            ])->all();

        // TODO: cần quy tắc tổng hợp thật (chưa nộp N bài liên tiếp / điểm giảm N bài gần nhất) —
        // để trong App\Services khi có, hiện trả rỗng để không hiển thị dữ liệu giả.
        $attentionStudents = [];

        // Quyền dạy (teacher_teaching) sắp hết hạn trong 30 ngày tới — cảnh báo sớm (7.2:
        // hết hạn thì không gắn/mở mới được học liệu này ở bất kỳ lớp nào).
        $soonestExpiring = $this->accessRights->forUserWithProduct($user->id)
            ->filter(fn ($ar) => $ar->scope === AccessScope::TeacherTeaching
                && $ar->isCurrentlyActive()
                && $ar->expires_at->lte(now()->addDays(30)))
            ->sortBy('expires_at')
            ->first();

        $accessExpiring = $soonestExpiring ? [
            'product' => $soonestExpiring->product->title ?? 'Học liệu',
            'daysLeft' => (int) now()->diffInDays($soonestExpiring->expires_at),
        ] : null;

        return [
            'upcoming' => $upcoming,
            'toOpen' => $toOpen,
            'attentionStudents' => $attentionStudents,
            'accessExpiring' => $accessExpiring,
        ];
    }
}
