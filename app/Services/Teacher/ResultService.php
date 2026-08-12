<?php

namespace App\Services\Teacher;

use App\Models\Assignment;
use App\Models\ClassRoom;
use App\Models\User;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;

/** Tổng hợp dữ liệu cho teacher.results.index — phễu Lớp → Đề → Học sinh → Lần nộp (10.2). */
class ResultService
{
    public function __construct(
        private readonly AssignmentRepositoryInterface $assignments,
        private readonly AttemptRepositoryInterface $attempts,
    ) {}

    public function funnelFor(User $user, ?int $requestedClassId, ?int $requestedAssignmentId): array
    {
        $classRooms = $user->classRoomsTeaching()->with('course')->get();

        $selectedClassId = $requestedClassId ?? ($classRooms->first()->id ?? 0);
        $selectedClassRoom = $classRooms->firstWhere('id', $selectedClassId);

        $assignments = $selectedClassRoom
            ? $this->assignments->forClassRoomWithAssessment($selectedClassRoom->id)
            : collect();

        $selectedAssignmentId = $requestedAssignmentId ?? ($assignments->first()->id ?? 0);
        $selectedAssignment = $assignments->firstWhere('id', $selectedAssignmentId);

        $students = collect();
        $stats = ['submitted' => 0, 'inProgress' => 0, 'notStarted' => 0];

        if ($selectedClassRoom && $selectedAssignment) {
            $funnel = $this->resultFunnelFor($selectedClassRoom, $selectedAssignment);
            $students = $funnel['students'];
            $stats = $funnel['stats'];
        }

        return [
            'classRooms' => $classRooms,
            'selectedClassId' => $selectedClassId,
            'assignments' => $assignments,
            'selectedAssignmentId' => $selectedAssignmentId,
            'students' => $students,
            'stats' => $stats,
        ];
    }

    /** Danh sách trạng thái từng học sinh + số liệu tổng hợp cho một lớp/đề đã chọn. */
    public function resultFunnelFor(ClassRoom $classRoom, Assignment $assignment): array
    {
        $roster = $classRoom->students;
        $attempts = $this->attempts->forAssignmentAndUserIds($assignment->id, $roster->pluck('id')->all());

        $students = $roster->map(function ($student) use ($attempts) {
            $attempt = $attempts->get($student->id);
            $status = match (true) {
                $attempt === null => 'Chưa làm',
                $attempt->submitted_at !== null => 'Đã nộp',
                default => 'Đang làm',
            };
            $tone = match ($status) {
                'Đã nộp' => 'success',
                'Đang làm' => 'info',
                default => 'neutral',
            };

            return [
                'id' => $student->id,
                'name' => $student->name,
                'status' => $status,
                'score' => $attempt?->total_score !== null ? (string) $attempt->total_score : '—',
                'tone' => $tone,
                'time' => $attempt?->submitted_at?->diffForHumans() ?? ($attempt ? 'Đang mở' : '—'),
            ];
        })->values();

        return [
            'students' => $students,
            'stats' => [
                'submitted' => $students->where('status', 'Đã nộp')->count(),
                'inProgress' => $students->where('status', 'Đang làm')->count(),
                'notStarted' => $students->where('status', 'Chưa làm')->count(),
            ],
        ];
    }
}
