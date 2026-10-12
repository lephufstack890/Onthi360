<?php

namespace App\Services\Teacher;

use App\Enums\ParentLinkStatus;
use App\Models\Assessment;
use App\Models\ClassAnnouncement;
use App\Models\ClassEnrollment;
use App\Models\ClassSession;
use App\Models\ParentLink;
use App\Models\PracticeAssignment;
use App\Models\Question;
use App\Models\User;
use App\Notifications\ClassAnnouncementPublished;
use App\Notifications\PracticeFeedbackSaved;
use App\Services\Public\PracticeAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 12/10 — dữ liệu cho khu GIÁO VIÊN dựng lại theo source mới (education-main:
 * TeacherDashboard / TeacherWorkPanel / TeacherAssignForm / TeacherNoticePanel).
 *
 * Bản mẫu chạy trên localStorage với lớp/học sinh minh hoạ; ở đây mọi số liệu lấy từ dữ liệu THẬT:
 *  - "lượt giao" = practice_assignments (mỗi dòng một học sinh), tiến độ lấy từ bài làm thật qua
 *    PracticeAssignmentService::managed() — không viết lại cách tính trạng thái;
 *  - lớp / học sinh = class_teachers + class_enrollments (đang học);
 *  - nhận xét bài nộp lưu ở practice_assignments.feedback (migration 001180);
 *  - thông báo lớp = class_announcements, đăng xong gửi thông báo hệ thống tới học sinh / phụ huynh.
 */
class TeacherWorkspaceService
{
    /** Trần số lượt giao đưa ra trang (mỗi loại bài/đề tối đa 300 ở managed()). */
    private const WORK_LIMIT = 300;

    /** Cắt phần mã nguồn / bài làm đưa ra giao diện để trang không phình. */
    private const SOURCE_CHARS = 2500;

    public function __construct(private readonly PracticeAssignmentService $practice) {}

    // ───────────────────────── Lượt giao bài & đề ─────────────────────────

    /**
     * Mọi lượt giao của giáo viên (bài tập + đề), mới nhất trước.
     *
     * @return list<array<string, mixed>>
     */
    public function workRows(User $teacher): array
    {
        $rows = [];

        foreach ([PracticeAssignment::TYPE_PROBLEM, PracticeAssignment::TYPE_EXAM] as $type) {
            foreach ($this->practice->managed($teacher, $type)['rows'] as $r) {
                $submitted = in_array($r['status'], ['completed', 'pending'], true);

                $rows[] = [
                    'id' => (int) $r['id'],
                    'studentId' => (int) ($r['studentId'] ?? 0),
                    'type' => $type,
                    'title' => (string) $r['title'],
                    'code' => (string) ($r['code'] ?? ''),
                    'student' => (string) $r['studentName'],
                    'account' => (string) $r['account'],
                    'teacher' => (string) $r['teacher'],
                    'assignedAt' => $r['assignedAt'],
                    'assignedTs' => (int) $r['assignedTs'],
                    'deadline' => $r['deadline'],
                    'status' => (string) $r['status'],
                    'late' => (bool) $r['late'],
                    'score' => $r['scoreLabel'],
                    'scoreAfter' => $r['scoreAfterLabel'],
                    'attempts' => (int) $r['attempts'],
                    'submittedAt' => $r['submittedAt'],
                    'startedAt' => $r['startedAt'],
                    'duration' => $r['duration'],
                    'result' => $r['resultLabel'],
                    'language' => $r['language'],
                    'tests' => $r['tests'],
                    'source' => $submitted && $r['source'] !== null ? mb_substr((string) $r['source'], 0, self::SOURCE_CHARS) : null,
                    'feedback' => $r['feedback'] ?? null,
                    'feedbackAt' => $r['feedbackAt'] ?? null,
                ];
            }
        }

        usort($rows, fn ($a, $b) => $b['assignedTs'] <=> $a['assignedTs']);

        return array_slice($rows, 0, self::WORK_LIMIT);
    }

    /** Nội dung có thể giao (bài tập đã xuất bản + đề luyện tập đã xuất bản). */
    public function catalog(): array
    {
        return [
            'problem' => Question::query()->where('status', 'published')->orderByDesc('id')->limit(400)
                ->get(['id', 'title', 'code'])
                ->map(fn ($q) => ['id' => $q->id, 'title' => (string) $q->title, 'code' => (string) ($q->code ?? '')])->all(),
            'exam' => Assessment::query()->where('type', 'practice')->where('status', 'published')->orderByDesc('id')->limit(300)
                ->get(['id', 'title', 'exam_code'])
                ->map(fn ($a) => ['id' => $a->id, 'title' => (string) $a->title, 'code' => (string) ($a->exam_code ?? '')])->all(),
        ];
    }

    /**
     * Lưu / sửa nhận xét của giáo viên cho một lượt giao đã có bài nộp.
     *
     * @throws ValidationException
     */
    public function saveFeedback(User $user, int $assignmentId, ?string $text): PracticeAssignment
    {
        if (! Schema::hasColumn('practice_assignments', 'feedback')) {
            throw ValidationException::withMessages(['feedback' => 'Chưa cập nhật cơ sở dữ liệu cho tính năng nhận xét. Hãy chạy php artisan migrate.']);
        }

        $assignment = PracticeAssignment::query()->find($assignmentId);

        if ($assignment === null || (! $this->practice->isAdmin($user) && (int) $assignment->assigned_by !== (int) $user->id)) {
            throw ValidationException::withMessages(['feedback' => 'Không tìm thấy lượt giao của bạn.']);
        }

        $text = trim((string) $text);

        if (mb_strlen($text) > 2000) {
            throw ValidationException::withMessages(['feedback' => 'Nhận xét tối đa 2000 ký tự.']);
        }

        $changed = $text !== trim((string) $assignment->feedback);

        $assignment->forceFill([
            'feedback' => $text !== '' ? $text : null,
            'feedback_at' => $text !== '' ? now() : null,
        ])->save();

        if ($changed && $text !== '' && $assignment->student !== null) {
            $assignment->student->notify(new PracticeFeedbackSaved($user));
        }

        return $assignment;
    }

    // ───────────────────────── Lớp & học sinh ─────────────────────────

    /** Ảnh bìa từng lớp (ảnh khoá học, thiếu thì xoay vòng ảnh mặc định) — giống thẻ lớp bên khu Học sinh. */
    public function covers(User $teacher): array
    {
        $defaults = ['course-img-1.jpg', 'course-img-2.jpg', 'course-img-3.jpg', 'course-img-4.jpg', 'course-img-5.jpg'];
        $out = [];

        foreach ($teacher->classRoomsTeaching()->with('course')->orderBy('class_rooms.id')->get() as $i => $c) {
            $out[(int) $c->id] = ($c->course?->coverUrl()) ?: asset('assets/'.$defaults[$i % count($defaults)]);
        }

        return $out;
    }

    /** Lớp giáo viên phụ trách (chỉ id / tên / mã) — cho các ô chọn lớp. */
    public function classOptions(User $teacher): array
    {
        return $teacher->classRoomsTeaching()->orderBy('class_rooms.name')->get()
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name, 'code' => (string) ($c->code ?? '')])->all();
    }

    /**
     * Học sinh đang học của từng lớp giáo viên phụ trách, kèm số lượt giao / đã nộp.
     *
     * @param  list<int>  $classIds
     * @param  list<array<string, mixed>>  $workRows
     * @return array<int, list<array<string, mixed>>>  khoá = id lớp
     */
    public function roster(array $classIds, array $workRows): array
    {
        if (empty($classIds)) {
            return [];
        }

        $assigned = [];
        $submitted = [];
        foreach ($workRows as $r) {
            $assigned[$r['studentId']] = ($assigned[$r['studentId']] ?? 0) + 1;
            if (in_array($r['status'], ['completed', 'pending'], true)) {
                $submitted[$r['studentId']] = ($submitted[$r['studentId']] ?? 0) + 1;
            }
        }

        $out = [];

        ClassEnrollment::query()
            ->whereIn('class_room_id', $classIds)
            ->where('status', ClassEnrollment::STATUS_ACTIVE)
            ->with('student:id,name,email,phone')
            ->get()
            ->each(function (ClassEnrollment $e) use (&$out, $assigned, $submitted) {
                if ($e->student === null) {
                    return;
                }

                $out[$e->class_room_id][] = [
                    'id' => $e->student->id,
                    'name' => (string) $e->student->name,
                    'account' => (string) ($e->student->email ?: ($e->student->phone ?: '—')),
                    'assigned' => (int) ($assigned[$e->student->id] ?? 0),
                    'submitted' => (int) ($submitted[$e->student->id] ?? 0),
                ];
            });

        foreach ($out as $classId => $students) {
            usort($students, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
            $out[$classId] = $students;
        }

        return $out;
    }

    // ───────────────────────── Tổng quan ─────────────────────────

    /**
     * Số liệu thật cho trang Tổng quan (4 thẻ số, buổi dạy tiếp theo, việc cần xử lý).
     *
     * @param  list<array<string, mixed>>  $workRows
     * @return array<string, mixed>
     */
    public function overview(User $teacher, array $workRows): array
    {
        $classRooms = $teacher->classRoomsTeaching()->withCount('students')->get();
        $classIds = $classRooms->pluck('id')->all();
        $studentCounts = $classRooms->pluck('students_count', 'id')->all();

        $studentTotal = empty($classIds) ? 0 : (int) ClassEnrollment::query()
            ->whereIn('class_room_id', $classIds)->where('status', ClassEnrollment::STATUS_ACTIVE)
            ->distinct()->count('student_id');

        $upcoming = [];
        $missingAttendance = 0;
        $firstMissingId = null;

        if (! empty($classIds)) {
            $upcoming = ClassSession::query()
                ->whereIn('class_room_id', $classIds)
                ->where('ends_at', '>', now())
                ->with('classRoom:id,name,code')
                ->orderBy('starts_at')
                ->limit(3)
                ->get()
                ->map(fn (ClassSession $s) => [
                    'id' => $s->id,
                    'classId' => $s->class_room_id,
                    'class' => (string) ($s->classRoom->name ?? ''),
                    'topic' => (string) ($s->topic ?: 'Buổi học'),
                    'time' => $s->starts_at->format('d/m/Y H:i').' – '.$s->ends_at->format('H:i'),
                    'live' => $s->starts_at->lte(now()),
                ])->all();

            // Buổi đã bắt đầu (60 ngày gần đây) mà số bản ghi điểm danh còn ít hơn sĩ số lớp.
            $past = ClassSession::query()
                ->whereIn('class_room_id', $classIds)
                ->where('starts_at', '<=', now())
                ->where('starts_at', '>=', now()->subDays(60))
                ->withCount('attendances')
                ->orderByDesc('starts_at')
                ->get();

            foreach ($past as $s) {
                if ((int) $s->attendances_count < (int) ($studentCounts[$s->class_room_id] ?? 0)) {
                    $missingAttendance++;
                    $firstMissingId ??= $s->id;
                }
            }
        }

        $pending = 0;
        $overdue = 0;
        $noFeedback = 0;
        foreach ($workRows as $r) {
            if ($r['status'] === 'pending') {
                $pending++;
            }
            if ($r['status'] === 'overdue') {
                $overdue++;
            }
            if (in_array($r['status'], ['completed', 'pending'], true) && empty($r['feedback'])) {
                $noFeedback++;
            }
        }

        $drafts = ClassAnnouncement::isReady()
            ? ClassAnnouncement::query()->where('teacher_id', $teacher->id)->where('state', ClassAnnouncement::STATE_DRAFT)->count()
            : 0;

        return [
            'classCount' => count($classIds),
            'studentTotal' => $studentTotal,
            'workTotal' => count($workRows),
            'pending' => $pending,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'missingAttendance' => $missingAttendance,
            'firstMissingSessionId' => $firstMissingId,
            'noFeedback' => $noFeedback,
            'drafts' => $drafts,
        ];
    }

    // ───────────────────────── Thông báo lớp ─────────────────────────

    /** Số học sinh / phụ huynh đã liên kết của từng lớp, để xem trước phạm vi gửi. */
    public function recipientCounts(array $classIds): array
    {
        $out = [];

        foreach ($classIds as $id) {
            $out[$id] = ['students' => 0, 'parents' => 0];
        }

        if (empty($classIds)) {
            return $out;
        }

        $enrollments = ClassEnrollment::query()
            ->whereIn('class_room_id', $classIds)
            ->where('status', ClassEnrollment::STATUS_ACTIVE)
            ->get(['class_room_id', 'student_id']);

        $links = ParentLink::query()
            ->whereIn('student_user_id', $enrollments->pluck('student_id')->unique()->all())
            ->where('status', ParentLinkStatus::Verified->value)
            ->get(['parent_user_id', 'student_user_id'])
            ->groupBy('student_user_id');

        foreach ($enrollments->groupBy('class_room_id') as $classId => $list) {
            $studentIds = $list->pluck('student_id')->unique();
            $parentIds = $studentIds->flatMap(fn ($sid) => ($links->get($sid) ?? collect())->pluck('parent_user_id'))->unique();
            $out[$classId] = ['students' => $studentIds->count(), 'parents' => $parentIds->count()];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function announcements(User $teacher): array
    {
        if (! ClassAnnouncement::isReady()) {
            return [];
        }

        return ClassAnnouncement::query()
            ->where('teacher_id', $teacher->id)
            ->with('classRoom:id,name')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (ClassAnnouncement $n) => [
                'id' => $n->id,
                'classId' => $n->class_room_id,
                'class' => (string) ($n->classRoom->name ?? 'Lớp đã xoá'),
                'audience' => $n->audience,
                'title' => (string) $n->title,
                'body' => (string) $n->body,
                'state' => $n->state,
                'students' => (int) $n->student_count,
                'parents' => (int) $n->parent_count,
                'updatedAt' => $n->updated_at?->format('d/m/Y H:i'),
                'publishedAt' => $n->published_at?->format('d/m/Y H:i'),
            ])->all();
    }

    /**
     * Lưu nháp hoặc đăng một thông báo lớp. Đăng xong thì gửi thông báo hệ thống.
     *
     * @param  array{id?: int|null, class_room_id: int, audience: string, title?: string|null, body?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function saveAnnouncement(User $teacher, array $data, bool $publish): ClassAnnouncement
    {
        if (! ClassAnnouncement::isReady()) {
            throw ValidationException::withMessages(['class_room_id' => 'Chưa cập nhật cơ sở dữ liệu cho thông báo lớp. Hãy chạy php artisan migrate.']);
        }

        $classRoom = $teacher->classRoomsTeaching()->where('class_rooms.id', (int) $data['class_room_id'])->first();

        if ($classRoom === null) {
            throw ValidationException::withMessages(['class_room_id' => 'Bạn không phụ trách lớp này.']);
        }

        $audience = $data['audience'] === ClassAnnouncement::AUDIENCE_FAMILIES
            ? ClassAnnouncement::AUDIENCE_FAMILIES
            : ClassAnnouncement::AUDIENCE_STUDENTS;

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if ($publish && ($title === '' || $body === '')) {
            throw ValidationException::withMessages(['title' => 'Nhập tiêu đề và nội dung trước khi đăng.']);
        }

        $announcement = null;

        if (! empty($data['id'])) {
            $announcement = ClassAnnouncement::query()
                ->where('teacher_id', $teacher->id)->where('id', (int) $data['id'])->first();

            if ($announcement === null) {
                throw ValidationException::withMessages(['title' => 'Thông báo này không còn trong danh sách.']);
            }

            if ($announcement->isPublished()) {
                throw ValidationException::withMessages(['title' => 'Thông báo đã đăng không sửa được.']);
            }
        }

        return DB::transaction(function () use ($teacher, $classRoom, $audience, $title, $body, $publish, $announcement) {
            $announcement ??= new ClassAnnouncement(['teacher_id' => $teacher->id]);
            $announcement->fill([
                'class_room_id' => $classRoom->id,
                'audience' => $audience,
                'title' => $title !== '' ? $title : null,
                'body' => $body !== '' ? $body : null,
            ]);

            if (! $publish) {
                $announcement->state = ClassAnnouncement::STATE_DRAFT;
                $announcement->save();

                return $announcement;
            }

            $studentIds = ClassEnrollment::query()
                ->where('class_room_id', $classRoom->id)->where('status', ClassEnrollment::STATUS_ACTIVE)
                ->pluck('student_id')->unique()->values();

            if ($studentIds->isEmpty()) {
                throw ValidationException::withMessages(['class_room_id' => 'Lớp này chưa có học sinh đang học để nhận thông báo.']);
            }

            $parentIds = $audience === ClassAnnouncement::AUDIENCE_FAMILIES
                ? ParentLink::query()->whereIn('student_user_id', $studentIds->all())
                    ->where('status', ParentLinkStatus::Verified->value)->pluck('parent_user_id')->unique()->values()
                : collect();

            $announcement->fill([
                'state' => ClassAnnouncement::STATE_PUBLISHED,
                'student_count' => $studentIds->count(),
                'parent_count' => $parentIds->count(),
                'published_at' => now(),
            ])->save();

            Notification::send(
                User::query()->whereIn('id', $studentIds->all())->get(),
                new ClassAnnouncementPublished($announcement, $classRoom, $teacher),
            );

            if ($parentIds->isNotEmpty()) {
                Notification::send(
                    User::query()->whereIn('id', $parentIds->all())->get(),
                    new ClassAnnouncementPublished($announcement, $classRoom, $teacher, forParent: true),
                );
            }

            return $announcement;
        });
    }

    /** Xoá một bản nháp (thông báo đã đăng thì giữ lại làm lịch sử). */
    public function deleteDraft(User $teacher, int $id): bool
    {
        if (! ClassAnnouncement::isReady()) {
            return false;
        }

        return (bool) ClassAnnouncement::query()
            ->where('teacher_id', $teacher->id)->where('id', $id)
            ->where('state', ClassAnnouncement::STATE_DRAFT)->delete();
    }
}
