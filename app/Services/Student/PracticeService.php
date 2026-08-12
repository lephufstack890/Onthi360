<?php

namespace App\Services\Student;

use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;

/** STU-04 — tabs Tự luyện · Theo lớp · Bài được giao · Đã lưu · Lịch sử. */
class PracticeService
{
    public function __construct(
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private AssessmentRepositoryInterface $assessments,
        private AssignmentRepositoryInterface $assignments,
        private AttemptRepositoryInterface $attempts,
    ) {}

    public function buildIndexData(User $user, string $tab): array
    {
        $classRoomIds = $this->classEnrollments->activeClassRoomIdsForUser($user->id);

        $counts = [
            'self' => $this->assessments->countPublishedPractice(),
            'class' => $this->assignments->countForClassRoomIds($classRoomIds),
            'assigned' => $this->assignments->countForClassRoomIds($classRoomIds, 'open'),
            'saved' => 0, // TODO: chưa có bảng "đã lưu/bookmark".
            'history' => $this->attempts->countSubmittedForUser($user->id),
        ];

        $tabs = [
            ['label' => 'Tự luyện', 'href' => route('student.practice.index'), 'active' => $tab === 'self', 'count' => $counts['self']],
            ['label' => 'Theo lớp', 'href' => route('student.practice.index', ['tab' => 'class']), 'active' => $tab === 'class', 'count' => $counts['class']],
            ['label' => 'Bài được giao', 'href' => route('student.practice.index', ['tab' => 'assigned']), 'active' => $tab === 'assigned', 'count' => $counts['assigned']],
            ['label' => 'Đã lưu', 'href' => route('student.practice.index', ['tab' => 'saved']), 'active' => $tab === 'saved', 'count' => $counts['saved']],
            ['label' => 'Lịch sử', 'href' => route('student.practice.index', ['tab' => 'history']), 'active' => $tab === 'history', 'count' => $counts['history']],
        ];

        $items = match ($tab) {
            'class' => $this->assignments->forClassRoomIds($classRoomIds, null, 30)
                ->map(fn ($a) => [
                    'title' => $a->assessment->title ?? 'Bài tập',
                    'type' => $a->assessment?->type?->value ?? '',
                    'source' => 'Lớp '.($a->classRoom->name ?? ''),
                    'difficulty' => '',
                    'status' => $a->isOpenNow() ? 'Đã mở' : 'Đã đóng',
                    'tone' => $a->isOpenNow() ? 'success' : 'neutral',
                    'takeRoute' => route('student.assessment.take', $a->assessment_id),
                ])->all(),
            'assigned' => $this->assignedTabItems($classRoomIds),
            'saved' => [], // TODO: chưa có bảng "đã lưu/bookmark".
            'history' => $this->attempts->recentSubmittedForUser($user->id, 30)
                ->map(fn ($attempt) => [
                    'title' => $attempt->assessment->title ?? 'Bài đã nộp',
                    'type' => $attempt->assessment?->type?->value ?? '',
                    'source' => ucfirst($attempt->source?->value ?? ''),
                    'difficulty' => '',
                    'status' => $attempt->total_score !== null ? 'Đã nộp — '.$attempt->total_score : 'Đang chấm',
                    'tone' => $attempt->is_provisional ? 'info' : 'success',
                    'takeRoute' => route('student.assessment.result', $attempt->id),
                ])->all(),
            default => $this->assessments->publishedPractice(30)
                ->map(fn ($a) => [
                    'title' => $a->title,
                    'type' => $a->type->value,
                    'source' => 'Tự luyện',
                    'difficulty' => '',
                    'status' => 'Chưa làm',
                    'tone' => 'info',
                    'takeRoute' => route('student.assessment.take', $a->id),
                ])->all(),
        };

        return ['tab' => $tab, 'tabs' => $tabs, 'items' => $items];
    }

    /**
     * Tab "Bài được giao": sắp xếp theo due_at tăng dần + eager-load ['assessment','classRoom']
     * — khác forClassRoomIds() (sắp xếp theo opens_at giảm dần, eager-load thêm classRoom.course)
     * nên dùng query() (van an toàn của repo) để giữ đúng hành vi cũ.
     */
    private function assignedTabItems(array $classRoomIds): array
    {
        return $this->assignments->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('status', 'open')
            ->with('assessment', 'classRoom')
            ->orderBy('due_at')
            ->limit(30)
            ->get()
            ->map(fn ($a) => [
                'title' => $a->assessment->title ?? 'Bài tập',
                'type' => $a->assessment?->type?->value ?? '',
                'source' => 'Lớp '.($a->classRoom->name ?? ''),
                'difficulty' => '',
                'status' => $a->due_at ? 'Hạn: '.$a->due_at->format('d/m H:i') : 'Đang mở',
                'tone' => 'warning',
                'takeRoute' => route('student.assessment.take', $a->assessment_id),
            ])->all();
    }
}
