<?php

namespace App\Services\Teacher;

use App\Models\Assignment;
use App\Models\ClassRoom;
use App\Models\User;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use Illuminate\Support\Collection;

/** Tổng hợp dữ liệu cho teacher.results.index — phễu Lớp → Đề → Học sinh → Lần nộp (10.2). */
class ResultService
{
    public function __construct(
        private readonly AssignmentRepositoryInterface $assignments,
        private readonly AttemptRepositoryInterface $attempts,
    ) {}

    public function funnelFor(User $user, ?int $requestedClassId, ?int $requestedAssignmentId, ?string $requestedStatus = null): array
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
            $stats = $funnel['stats'];
            $students = $this->filterByStatus($funnel['students'], $requestedStatus);
        }

        return [
            'classRooms' => $classRooms,
            'selectedClassId' => $selectedClassId,
            'assignments' => $assignments,
            'selectedAssignmentId' => $selectedAssignmentId,
            'selectedStatus' => $requestedStatus ?? '',
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
                'attemptId' => $attempt?->id,
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

    /** teacher.results.attempt — chi tiết một lần nộp, chỉ khi lần nộp đó thuộc lớp giáo viên đang dạy. */
    public function attemptDetailFor(User $teacher, int $attemptId): array
    {
        $attempt = $this->attempts->withAnswersAndAssessment($attemptId);
        abort_if($attempt === null, 404);

        $taughtClassIds = $teacher->classRoomsTeaching()->pluck('class_rooms.id');
        abort_unless($attempt->class_room_id !== null && $taughtClassIds->contains($attempt->class_room_id), 403);

        $attempt->loadMissing('user', 'classRoom');

        $answers = $attempt->answers->map(function ($answer, $idx) {
            $verdictLabel = match ($answer->verdict?->value) {
                'accepted' => 'Đúng',
                'wrong_answer' => 'Sai',
                'pending', 'queued', 'judging' => 'Đang chấm',
                'time_limit_exceeded' => 'Quá thời gian',
                'memory_limit_exceeded' => 'Quá bộ nhớ',
                'runtime_error' => 'Lỗi thực thi',
                'compile_error' => 'Lỗi biên dịch',
                'system_error' => 'Lỗi hệ thống',
                default => $answer->verdict?->value ?? '—',
            };
            $tone = match (true) {
                $answer->verdict?->value === 'accepted' => 'success',
                in_array($answer->verdict?->value, ['pending', 'queued', 'judging'], true) => 'info',
                $answer->verdict === null => 'neutral',
                default => 'danger',
            };

            return [
                'no' => $idx + 1,
                'question' => $answer->question,
                'type' => $answer->question?->type?->value ?? '',
                'submitted' => $this->formatSubmittedAnswer($answer),
                'verdict' => $verdictLabel,
                'tone' => $tone,
                'score' => $answer->score !== null ? (string) $answer->score : '—',
            ];
        })->values();

        return [
            'attempt' => $attempt,
            'answers' => $answers,
        ];
    }

    /** Hiển thị nội dung bài làm của học sinh theo từng loại câu (mcq/fill_blank dùng `answer`, coding dùng `code_source`). */
    private function formatSubmittedAnswer($answer): string
    {
        if ($answer->question?->type?->value === 'coding') {
            return $answer->code_source !== null
                ? ($answer->language ? "[{$answer->language}]
" : '').$answer->code_source
                : '(chưa nộp bài làm)';
        }

        $data = $answer->answer;
        if (! is_array($data) || $data === []) {
            return '(chưa trả lời)';
        }

        $parts = [];
        foreach ($data as $key => $value) {
            $parts[] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return implode(', ', $parts);
    }

    /** teacher.results.export — CSV kết quả theo bộ lọc hiện tại (không hứa "Excel" khi chưa có thư viện xlsx). */
    public function exportCsv(User $teacher, ?int $requestedClassId, ?int $requestedAssignmentId, ?string $requestedStatus = null): string
    {
        $data = $this->funnelFor($teacher, $requestedClassId, $requestedAssignmentId, $requestedStatus);

        $lines = ["Học sinh,Trạng thái,Điểm,Thời gian"];
        foreach ($data['students'] as $s) {
            $lines[] = implode(',', [
                $this->csvField($s['name']),
                $this->csvField($s['status']),
                $this->csvField($s['score']),
                $this->csvField($s['time']),
            ]);
        }

        // BOM để Excel nhận đúng UTF-8 tiếng Việt khi mở file CSV.
        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }

    private function csvField(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }

    private function filterByStatus(Collection $students, ?string $status): Collection
    {
        if (! $status) {
            return $students;
        }

        return $students->where('status', $status)->values();
    }
}
