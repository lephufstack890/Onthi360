<?php

namespace App\Services\Student;

use App\Models\User;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;

/**
 * STU-01 — tổng hợp dữ liệu cho trang dashboard học sinh (xem docblock cũ ở
 * DashboardController). $todayTasks/$upcoming/$notifications vẫn là mock có
 * TODO vì chưa có bảng nghiệp vụ tương ứng.
 */
class DashboardService
{
    public function __construct(
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private AttemptRepositoryInterface $attempts,
    ) {}

    public function buildDashboardData(User $user): array
    {
        $enrollments = $this->classEnrollments->activeForUser($user->id, ['classRoom.course']);

        $hasAnyClass = $enrollments->isNotEmpty();

        $classProgress = $enrollments->map(function ($enrollment) {
            $classRoom = $enrollment->classRoom;

            return [
                'name' => trim(($classRoom->course->title ?? '').' · '.($classRoom->name ?? '')),
                // TODO: tính % thật theo progress_unlocks đã hoàn thành / tổng mã bài đã mở.
                'percent' => 50,
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
}
