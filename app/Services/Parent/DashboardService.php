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
 * tới, 1 query đếm buổi đã qua, 1 query đếm điểm danh, 1 query bài đã nộp,
 * 1 query tiến độ buổi học (cho % tiến độ lớp) — thay cho lặp lại theo số con.
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
        $sessionProgressByClassRoom = $this->sessionProgressByClassRoom($classRoomIds);

        $children = $links->map(function ($link) use (
            $enrollmentsByStudent,
            $nextSessionsByClassRoom,
            $pastCountsByClassRoom,
            $presentCountsByStudent,
            $sessionProgressByClassRoom,
        ) {
            $child = $link->student;
            $enrollment = $enrollmentsByStudent->get($child->id);
            $classRoom = $enrollment?->classRoom;

            $nextSession = $classRoom ? $nextSessionsByClassRoom->get($classRoom->id) : null;
            $totalSessions = $classRoom ? (int) ($pastCountsByClassRoom->get($classRoom->id) ?? 0) : 0;
            $presentSessions = $classRoom ? (int) ($presentCountsByStudent->get($child->id) ?? 0) : 0;

            $progress = $classRoom ? $sessionProgressByClassRoom->get($classRoom->id) : null;
            $progressTotalSessions = (int) ($progress->total ?? 0);
            $progressEndedSessions = (int) ($progress->ended ?? 0);

            return [
                'id' => $child->id,
                'name' => $child->name,
                'class' => $classRoom->name ?? 'Chưa có lớp',
                'nextSession' => $nextSession?->starts_at->format('d/m H:i') ?? 'Chưa có buổi học sắp tới',
                'attendance' => $this->attendanceRatioLabel($presentSessions, $totalSessions),
                'progress' => $this->completionPercent($progressEndedSessions, $progressTotalSessions),
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

    /**
     * "Tiến độ lớp" = % buổi học ĐÃ KẾT THÚC trên tổng số buổi đã lên lịch cho lớp con
     * đang học — CÙNG công thức đã được xác nhận và dùng thật ở
     * App\Services\Teacher\ClassRoomService::completionPercent(). Trước đây con số này bị
     * hardcode 0 (kèm TODO tính theo progress_unlocks + attempts), khiến thanh tiến độ luôn
     * đứng yên ở 0% bất kể lớp đã học tới đâu — không phải lỗi hiển thị mà là chưa nối dữ
     * liệu thật. Không dùng công thức theo attempts đã nộp vì rà soát toàn bộ codebase xác
     * nhận KHÔNG có luồng nào tạo Attempt đã nộp thật (xem TODO trong
     * AssessmentService::buildTakeData()) nên số đó sẽ luôn là 0% dù đổi công thức gì.
     */
    private function completionPercent(int $endedSessions, int $totalSessions): int
    {
        if ($totalSessions <= 0) {
            return 0;
        }

        return (int) round(min($endedSessions, $totalSessions) / $totalSessions * 100);
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

    /** @return \Illuminate\Support\Collection<int, object{class_room_id:int,total:int,ended:int}> keyBy class_room_id */
    private function sessionProgressByClassRoom(array $classRoomIds): \Illuminate\Support\Collection
    {
        if (empty($classRoomIds)) {
            return collect();
        }

        return $this->classSessions->sessionProgressCountsForClassRoomIds($classRoomIds)->keyBy('class_room_id');
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
