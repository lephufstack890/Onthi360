<?php

namespace App\Services\Parent;

use App\Models\User;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Repositories\Contracts\ParentLinkRepositoryInterface;

/**
 * Dữ liệu cho parent.dashboard (PAR-01) — chỉ con đã liên kết + xác minh (10.3).
 *
 * Gộp mọi truy vấn theo-từng-con thành truy vấn theo-lô (whereIn) để tránh
 * N+1 khi phụ huynh có nhiều con: 1 query enrollment, 1 query buổi học sắp
 * tới, 1 query đếm buổi đã qua, 1 query đếm điểm danh, 1 query bài đã nộp —
 * thay cho 5 query lặp lại theo số con như bản cũ.
 */
class DashboardService
{
    public function __construct(
        private ParentLinkRepositoryInterface $parentLinks,
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private ClassSessionRepositoryInterface $classSessions,
        private AttendanceRepositoryInterface $attendances,
        private AttemptRepositoryInterface $attempts,
    ) {}

    public function buildDashboard(User $user): array
    {
        $links = $this->parentLinks->verifiedForParentWithStudent($user->id);

        $studentIds = $links->map(fn ($link) => $link->student->id)->all();

        $enrollmentsByStudent = $this->activeEnrollmentsByStudent($studentIds);
        $classRoomIds = $enrollmentsByStudent->pluck('class_room_id')->unique()->values()->all();

        $nextSessionsByClassRoom = $this->nextSessionsByClassRoom($classRoomIds);
        $pastCountsByClassRoom = $this->pastSessionCountsByClassRoom($classRoomIds);
        $presentCountsByStudent = $this->presentCountsByStudent($studentIds, $classRoomIds);
        $recentAttemptsByStudent = $this->recentAttemptsByStudent($studentIds);

        $children = $links->map(function ($link) use (
            $enrollmentsByStudent,
            $nextSessionsByClassRoom,
            $pastCountsByClassRoom,
            $presentCountsByStudent,
        ) {
            $child = $link->student;
            $enrollment = $enrollmentsByStudent->get($child->id);
            $classRoom = $enrollment?->classRoom;

            $nextSession = $classRoom ? $nextSessionsByClassRoom->get($classRoom->id) : null;
            $totalSessions = $classRoom ? (int) ($pastCountsByClassRoom->get($classRoom->id) ?? 0) : 0;
            $presentSessions = $classRoom ? (int) ($presentCountsByStudent->get($child->id) ?? 0) : 0;

            return [
                'id' => $child->id,
                'name' => $child->name,
                'class' => $classRoom->name ?? 'Chưa có lớp',
                'nextSession' => $nextSession?->starts_at->format('d/m H:i') ?? 'Chưa có buổi học sắp tới',
                'attendance' => $this->attendanceRatioLabel($presentSessions, $totalSessions),
                // TODO: % tiến độ thật cần công thức tổng hợp progress_unlocks + attempts theo lớp.
                'progress' => 0,
            ];
        })->values()->all();

        $recentResults = [];
        foreach ($links as $link) {
            $child = $link->student;
            $attempts = ($recentAttemptsByStudent->get($child->id) ?? collect())->take(3);
            foreach ($attempts as $attempt) {
                $recentResults[] = [
                    'child' => $child->name,
                    'title' => $attempt->assessment->title ?? 'Bài đã nộp',
                    'score' => $attempt->total_score !== null ? (string) $attempt->total_score : 'Đang chấm',
                    'tone' => $attempt->is_provisional ? 'info' : 'success',
                    'time' => $attempt->submitted_at?->diffForHumans(),
                ];
            }
        }

        return ['children' => $children, 'recentResults' => $recentResults];
    }

    /** "3/5 buổi" — tỉ lệ điểm danh dùng chung cho dashboard tổng quan lẫn chi tiết con. */
    public function attendanceRatioLabel(int $present, int $total): string
    {
        return $total > 0 ? "{$present}/{$total} buổi" : 'Chưa có dữ liệu';
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\ClassEnrollment> keyBy student_id */
    private function activeEnrollmentsByStudent(array $studentIds): \Illuminate\Support\Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        return $this->classEnrollments->query()
            ->whereIn('student_id', $studentIds)
            ->where('status', 'active')
            ->with('classRoom')
            ->get()
            ->keyBy('student_id');
    }

    private function nextSessionsByClassRoom(array $classRoomIds): \Illuminate\Support\Collection
    {
        if (empty($classRoomIds)) {
            return collect();
        }

        return $this->classSessions->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->get()
            ->groupBy('class_room_id')
            ->map(fn ($group) => $group->first());
    }

    private function pastSessionCountsByClassRoom(array $classRoomIds): \Illuminate\Support\Collection
    {
        if (empty($classRoomIds)) {
            return collect();
        }

        return $this->classSessions->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('starts_at', '<', now())
            ->selectRaw('class_room_id, count(*) as cnt')
            ->groupBy('class_room_id')
            ->pluck('cnt', 'class_room_id');
    }

    private function presentCountsByStudent(array $studentIds, array $classRoomIds): \Illuminate\Support\Collection
    {
        if (empty($studentIds) || empty($classRoomIds)) {
            return collect();
        }

        return $this->attendances->query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['present', 'late'])
            ->whereHas('classSession', fn ($q) => $q->whereIn('class_room_id', $classRoomIds))
            ->selectRaw('student_id, count(*) as cnt')
            ->groupBy('student_id')
            ->pluck('cnt', 'student_id');
    }

    private function recentAttemptsByStudent(array $studentIds): \Illuminate\Support\Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        return $this->attempts->query()
            ->whereIn('user_id', $studentIds)
            ->whereNotNull('submitted_at')
            ->with('assessment')
            ->latest('submitted_at')
            ->get()
            ->groupBy('user_id');
    }
}
