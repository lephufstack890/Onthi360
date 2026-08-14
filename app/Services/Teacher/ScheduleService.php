<?php

namespace App\Services\Teacher;

use App\Enums\AttendanceStatus;
use App\Enums\SessionResourceType;
use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\ClassSession;
use App\Models\Role;
use App\Models\SessionResource;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassMaterialRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\SessionResourceRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tổng hợp dữ liệu cho teacher.schedule.* — lịch buổi học + điểm danh xuyên các lớp giáo
 * viên phụ trách (TEA-01/02, spec 8.2 "Lớp học: ... lịch, điểm danh, thông báo").
 */
class ScheduleService
{
    public function __construct(
        private readonly ClassRoomRepositoryInterface $classRooms,
        private readonly ClassSessionRepositoryInterface $classSessions,
        private readonly AttendanceRepositoryInterface $attendances,
        private readonly SessionResourceRepositoryInterface $sessionResources,
        private readonly ClassMaterialRepositoryInterface $classMaterials,
        private readonly QuestionRepositoryInterface $questions,
        private readonly AssessmentRepositoryInterface $assessments,
    ) {}

    /** teacher.schedule.index — toàn bộ buổi học của mọi lớp giáo viên phụ trách, sắp tới trước. */
    public function indexData(User $teacher): array
    {
        $classRooms = $teacher->classRoomsTeaching()->withCount('students')->get();
        $classRoomIds = $classRooms->pluck('id')->all();
        $rosterCountByClassRoomId = $classRooms->pluck('students_count', 'id');

        $sessions = $this->classSessions->allForClassRoomIds($classRoomIds);

        $upcoming = $sessions->filter(fn (ClassSession $s) => $s->starts_at !== null && $s->starts_at->gte(now()))->sortBy('starts_at')->values();
        $past = $sessions->filter(fn (ClassSession $s) => $s->starts_at === null || $s->starts_at->lt(now()))->sortByDesc('starts_at')->values();

        return [
            'classRooms' => $classRooms,
            'upcoming' => $upcoming->map(fn ($s) => $this->mapSession($s, $rosterCountByClassRoomId))->all(),
            'past' => $past->map(fn ($s) => $this->mapSession($s, $rosterCountByClassRoomId))->all(),
        ];
    }

    /** Dùng cho tab "Lịch/Điểm danh" trong teacher.classes.show — chỉ buổi học của MỘT lớp. */
    public function sessionsForClassRoom(ClassRoom $classRoom): array
    {
        $rosterCount = collect([$classRoom->id => $classRoom->students()->count()]);
        $sessions = $this->classSessions->allForClassRoom($classRoom->id);

        return [
            'sessions' => $sessions->sortByDesc('starts_at')->map(fn ($s) => $this->mapSession($s, $rosterCount))->values()->all(),
        ];
    }

    private function mapSession(ClassSession $session, Collection $rosterCountByClassRoomId): array
    {
        $total = (int) ($rosterCountByClassRoomId->get($session->class_room_id) ?? 0);
        $attendances = $session->attendances ?? collect();
        $taken = $attendances->count();
        $present = $attendances->where('status', AttendanceStatus::Present)->count();

        [$timeStatusLabel, $timeStatusTone] = $this->timeStatus($session);

        return [
            'id' => $session->id,
            'classRoomId' => $session->class_room_id,
            'className' => $session->classRoom->name ?? '',
            'topic' => $session->topic,
            'location' => $session->location,
            'startsAt' => $session->starts_at,
            'endsAt' => $session->ends_at,
            // Hiển thị đủ CẢ giờ bắt đầu lẫn giờ kết thúc trong 1 cột cho gọn bảng.
            'timeRangeLabel' => $this->timeRangeLabel($session),
            'timeStatusLabel' => $timeStatusLabel,
            'timeStatusTone' => $timeStatusTone,
            'attendanceTaken' => $taken > 0,
            'attendanceSummary' => match (true) {
                $taken > 0 => "{$present}/{$total} có mặt",
                $total > 0 => "Chưa điểm danh ({$total} học sinh)",
                default => 'Chưa điểm danh',
            },
        ];
    }

    /** "13/08 08:00 - 09:30" (cùng ngày) hoặc "13/08 08:00 - 14/08 01:00" (khác ngày). */
    private function timeRangeLabel(ClassSession $session): string
    {
        if ($session->starts_at === null) {
            return '—';
        }

        if ($session->ends_at === null) {
            return $session->starts_at->format('d/m H:i');
        }

        $sameDay = $session->starts_at->isSameDay($session->ends_at);

        return $sameDay
            ? $session->starts_at->format('d/m H:i').' - '.$session->ends_at->format('H:i')
            : $session->starts_at->format('d/m H:i').' - '.$session->ends_at->format('d/m H:i');
    }

    /**
     * Trạng thái buổi học theo thời gian THỰC (so với now() — đã đúng múi giờ VN từ khi
     * config/app.php được thêm lại, xem ghi chú ở đó) — không dựa vào bucket "sắp
     * tới/đã qua" (bucket đó chỉ so theo starts_at, một buổi ĐANG diễn ra đã rơi vào
     * "đã qua" dù chưa kết thúc — cần nhãn riêng để giáo viên phân biệt rõ).
     *
     * @return array{0: string, 1: string}
     */
    private function timeStatus(ClassSession $session): array
    {
        $now = now();

        if ($session->starts_at !== null && $now->lt($session->starts_at)) {
            return ['Sắp diễn ra', 'info'];
        }

        if ($session->ends_at !== null && $now->gt($session->ends_at)) {
            return ['Đã kết thúc', 'neutral'];
        }

        return ['Đang diễn ra', 'warning'];
    }

    /** teacher.schedule.store — tạo buổi học mới cho một lớp đang dạy (kiểm tra lại quyền, 16 mục 3). */
    public function store(User $teacher, array $data): ClassSession
    {
        $classRoom = $this->findTaughtClassRoom($teacher, (int) $data['class_room_id']);

        return $this->classSessions->create([
            'class_room_id' => $classRoom->id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'topic' => $data['topic'] ?? null,
            'location' => $data['location'] ?? null,
        ]);
    }

    /**
     * teacher.schedule.attendance — roster đầy đủ của lớp + trạng thái điểm danh hiện có
     * (mặc định "present"), kèm nhận xét (mặc định 1 câu chung, note họp 13/8) + cột "Em
     * cần học thêm" + nguồn điểm danh (manual/auto — auto là học sinh tự vào làm bài lúc
     * buổi học đang diễn ra, xem App\Services\AttemptService::autoCheckIn()) để giáo viên
     * chỉ cần tập trung xử lý các dòng CHƯA tự động. Kèm luôn danh sách tài nguyên đã gắn
     * buổi này + tùy chọn để thêm mới (note họp 13/8, mục 3: "Gắn tài liệu, câu hỏi, đề
     * thi, video, link, … vào 1 buổi học cụ thể").
     */
    public function attendanceForSession(User $teacher, int $sessionId): array
    {
        $session = $this->classSessions->find($sessionId)
            ?? throw (new ModelNotFoundException())->setModel(ClassSession::class, [$sessionId]);
        $classRoom = $this->findTaughtClassRoom($teacher, $session->class_room_id);

        $roster = $classRoom->students;
        $existing = $this->attendances->forClassSession($session->id);

        $rows = $roster->map(function ($student) use ($existing) {
            $record = $existing->get($student->id);

            return [
                'studentId' => $student->id,
                'name' => $student->name,
                'status' => $record?->status?->value ?? 'present',
                'source' => $record?->source?->value ?? 'manual',
                'note' => $record?->note ?? Attendance::DEFAULT_NOTE,
                'needsMorePractice' => $record?->needs_more_practice ?? false,
            ];
        })->values()->all();

        return [
            'session' => $session,
            'classRoom' => $classRoom,
            'rows' => $rows,
        ] + $this->resourcePickerData($teacher, $session);
    }

    /**
     * Danh sách tài nguyên đã gắn buổi học này + các lựa chọn có thể thêm (chỉ tài liệu đã
     * gắn lớp / câu hỏi & đề thi của chính giáo viên — không cho chọn bản ghi không thuộc
     * quyền quản lý của giáo viên).
     */
    private function resourcePickerData(User $teacher, ClassSession $session): array
    {
        $resources = $this->sessionResources->forClassSession($session->id)
            ->map(fn (SessionResource $r) => [
                'id' => $r->id,
                'type' => $r->type->value,
                'typeLabel' => $r->type->label(),
                'title' => $r->displayTitle(),
                'url' => $r->url,
                'note' => $r->note,
            ])->values()->all();

        $materialOptions = $this->classMaterials->activeForClassRoom($session->class_room_id)
            ->map(fn ($cm) => ['id' => $cm->material_id, 'title' => $cm->material->title ?? '(học liệu)'])
            ->values()->all();

        $questionOptions = $this->questions->byOwner($teacher->id, 'published', 200)
            ->map(fn ($q) => ['id' => $q->id, 'title' => Str::limit($q->title ?? '', 60)])
            ->values()->all();

        $assessmentOptions = $this->assessments->byOwner($teacher->id, 200)
            ->map(fn ($a) => ['id' => $a->id, 'title' => $a->title])
            ->values()->all();

        return [
            'sessionResources' => $resources,
            'materialOptions' => $materialOptions,
            'questionOptions' => $questionOptions,
            'assessmentOptions' => $assessmentOptions,
        ];
    }

    /**
     * teacher.schedule.resources.save — gắn 1 tài nguyên (tài liệu/câu hỏi/đề thi/video/
     * link/ghi chú) vào buổi học này (note họp 13/8, mục 3). Với tài liệu/câu hỏi/đề thi,
     * kiểm tra lại quyền sở hữu/gắn lớp NGAY TẠI THỜI ĐIỂM lưu — không tin id gửi từ danh
     * sách đã hiển thị trước đó trên UI (16 mục 3).
     */
    public function addResource(User $teacher, int $sessionId, array $data): void
    {
        $session = $this->classSessions->find($sessionId)
            ?? throw (new ModelNotFoundException())->setModel(ClassSession::class, [$sessionId]);
        $classRoom = $this->findTaughtClassRoom($teacher, $session->class_room_id);

        $type = SessionResourceType::tryFrom($data['type'] ?? '');

        if ($type === null) {
            throw ValidationException::withMessages(['type' => 'Loại tài nguyên không hợp lệ.']);
        }

        $attrs = [
            'class_session_id' => $session->id,
            'type' => $type->value,
            'added_by' => $teacher->id,
        ];

        switch ($type) {
            case SessionResourceType::Material:
                $materialId = (int) ($data['material_id'] ?? 0);
                $stillAttached = $this->classMaterials->query()
                    ->where('class_room_id', $classRoom->id)
                    ->where('status', 'active')
                    ->where('material_id', $materialId)
                    ->exists();

                if (! $stillAttached) {
                    throw ValidationException::withMessages(['material_id' => 'Tài liệu này không (còn) gắn với lớp.']);
                }

                $attrs['material_id'] = $materialId;
                break;

            case SessionResourceType::Question:
                $questionId = (int) ($data['question_id'] ?? 0);
                $owned = $this->questions->query()
                    ->where('id', $questionId)
                    ->where('owner_id', $teacher->id)
                    ->exists();

                if (! $owned) {
                    throw ValidationException::withMessages(['question_id' => 'Câu hỏi không hợp lệ.']);
                }

                $attrs['question_id'] = $questionId;
                break;

            case SessionResourceType::Assessment:
                $assessmentId = (int) ($data['assessment_id'] ?? 0);
                $owned = $this->assessments->query()
                    ->where('id', $assessmentId)
                    ->where('owner_type', 'teacher')
                    ->where('owner_id', $teacher->id)
                    ->exists();

                if (! $owned) {
                    throw ValidationException::withMessages(['assessment_id' => 'Đề thi/bài tập không hợp lệ.']);
                }

                $attrs['assessment_id'] = $assessmentId;
                break;

            default: // video / link / note — nhập tay
                if (blank($data['title'] ?? null)) {
                    throw ValidationException::withMessages(['title' => 'Cần nhập tiêu đề.']);
                }

                $attrs['title'] = $data['title'];
                $attrs['url'] = $data['url'] ?? null;
                $attrs['note'] = $data['note'] ?? null;
                break;
        }

        $this->sessionResources->create($attrs);
    }

    /** teacher.schedule.resources.delete — gỡ 1 tài nguyên khỏi buổi học. */
    public function removeResource(User $teacher, int $sessionId, int $resourceId): void
    {
        $session = $this->classSessions->find($sessionId)
            ?? throw (new ModelNotFoundException())->setModel(ClassSession::class, [$sessionId]);
        $this->findTaughtClassRoom($teacher, $session->class_room_id);

        $resource = $this->sessionResources->findOrFail($resourceId);
        abort_unless((int) $resource->class_session_id === $session->id, 404);

        $this->sessionResources->delete($resource);
    }

    /**
     * teacher.schedule.attendance.save — lưu điểm danh + nhận xét + "Em cần học thêm". Chỉ
     * ghi cho học sinh THỰC SỰ thuộc roster lớp này tại thời điểm lưu (không tin id gửi từ
     * client, 16 mục 3). $notes/$needsMorePractice keyed theo student_id giống $statuses.
     */
    public function saveAttendance(User $teacher, int $sessionId, array $statuses, array $notes = [], array $needsMorePractice = []): void
    {
        $session = $this->classSessions->find($sessionId)
            ?? throw (new ModelNotFoundException())->setModel(ClassSession::class, [$sessionId]);
        $classRoom = $this->findTaughtClassRoom($teacher, $session->class_room_id);

        $rosterIds = $classRoom->students->pluck('id')->all();
        $existing = $this->attendances->forClassSession($session->id);

        foreach ($statuses as $studentId => $status) {
            $studentId = (int) $studentId;
            if (! in_array($studentId, $rosterIds, true)) {
                continue;
            }
            if (! in_array($status, ['present', 'absent', 'excused', 'late'], true)) {
                continue;
            }

            $attrs = [
                'status' => $status,
                'recorded_by' => $teacher->id,
                'note' => $notes[$studentId] ?? null,
                'needs_more_practice' => (bool) ($needsMorePractice[$studentId] ?? false),
            ];

            $record = $existing->get($studentId);
            if ($record !== null) {
                // Điểm danh tay của giáo viên "thắng" điểm danh tự động trước đó — cập
                // nhật cả source về manual vì giáo viên đã trực tiếp xác nhận dòng này.
                $this->attendances->update($record, $attrs + ['source' => 'manual']);
            } else {
                $this->attendances->create($attrs + [
                    'class_session_id' => $session->id,
                    'student_id' => $studentId,
                    'source' => 'manual',
                ]);
            }
        }
    }

    /** teacher.schedule.summary.save — "tổng kết buổi học" (note họp 13/8). */
    public function saveSummary(User $teacher, int $sessionId, ?string $summary): void
    {
        $session = $this->classSessions->find($sessionId)
            ?? throw (new ModelNotFoundException())->setModel(ClassSession::class, [$sessionId]);
        $this->findTaughtClassRoom($teacher, $session->class_room_id);

        $session->update(['summary' => $summary]);
    }

    private function findTaughtClassRoom(User $teacher, int $classId): ClassRoom
    {
        $classRoom = $this->classRooms->findOrFail($classId);
        abort_unless($classRoom->isTaughtBy($teacher) || $teacher->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN), 403);

        return $classRoom;
    }
}
