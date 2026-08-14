<?php

namespace App\Services\Student;

use App\Models\User;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;

/**
 * STU-01 — tổng hợp dữ liệu cho trang dashboard học sinh (xem docblock cũ ở
 * DashboardController). $todayTasks/$upcoming/$notifications vẫn là mock có
 * TODO vì chưa có bảng nghiệp vụ tương ứng.
 */
class DashboardService
{
    public function __construct(
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private ClassSessionRepositoryInterface $classSessions,
        private AttemptRepositoryInterface $attempts,
    ) {}

    public function buildDashboardData(User $user): array
    {
        $enrollments = $this->classEnrollments->activeForUser($user->id, ['classRoom.course']);

        $hasAnyClass = $enrollments->isNotEmpty();

        $classRoomIds = $enrollments->map(fn ($e) => $e->classRoom?->id)->filter()->unique()->values()->all();
        $sessionProgressByClassRoom = empty($classRoomIds)
            ? collect()
            : $this->classSessions->sessionProgressCountsForClassRoomIds($classRoomIds)->keyBy('class_room_id');

        $classProgress = $enrollments->map(function ($enrollment) use ($sessionProgressByClassRoom) {
            $classRoom = $enrollment->classRoom;
            $progress = $classRoom ? $sessionProgressByClassRoom->get($classRoom->id) : null;

            return [
                'name' => trim(($classRoom->course->title ?? '').' · '.($classRoom->name ?? '')),
                // "% tiến độ lớp" — % buổi học đã kết thúc / tổng số buổi đã lên lịch, CÙNG
                // công thức đã dùng thật ở
                // App\Services\Teacher\ClassRoomService::completionPercent() (xem giải thích
                // đầy đủ ở đó). Trước đây hardcode cứng 50 bất kể lớp nào — không phải % thật.
                'percent' => $this->completionPercent((int) ($progress->ended ?? 0), (int) ($progress->total ?? 0)),
            ];
        })->values()->all();

        $recentResults = $this->attempts->recentSubmittedForUser($user->id, 5)
            ->map(fn ($attempt) => [
                'title' => $attempt->assessment->title ?? 'Bài đã nộp',
                'score' => $attempt->total_score !== null ? (string) $attempt->total_score : 'Đang chấm',
                'time' => $attempt->submitted_at?->diffForHumans(),
                'tone' => $attempt->is_provisional ? 'info' : 'success',
            ])->all();

        // TODO: thay bằng dữ liệu thật khi có bảng notifications và luật
        // gộp assignment/progress_unlock sắp tới hạn (16 mục 4, 16 mục 9).
        return [
            'name' => $user->name,
            'hasAnyClass' => $hasAnyClass,
            'todayTasks' => [],
            'upcoming' => [],
            'classProgress' => $classProgress,
            'recentResults' => $recentResults,
            'notifications' => [],
        ];
    }

    /** Xem giải thích đầy đủ ở App\Services\Teacher\ClassRoomService::completionPercent(). */
    private function completionPercent(int $endedSessions, int $totalSessions): int
    {
        if ($totalSessions <= 0) {
            return 0;
        }

        return (int) round(min($endedSessions, $totalSessions) / $totalSessions * 100);
    }
}
