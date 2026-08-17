<?php

namespace App\Services\Student;

use App\Models\ClassSession;
use App\Models\User;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * student.schedule.index — "Thời khoá biểu" dạng lưới tuần (Thứ Hai → Chủ Nhật, có ngày cụ
 * thể từng cột), GỘP buổi học của TẤT CẢ lớp học sinh đang tham gia vào 1 bảng — khác với tab
 * "Lịch học" trong student.classes.show (App\Services\Student\ClassRoomService
 * ::buildScheduleTab()) chỉ hiển thị buổi học của MỘT lớp dạng danh sách phẳng.
 *
 * Nhãn điểm danh (ATTENDANCE_LABELS) lặp lại có chủ đích giống ClassRoomService — theo quy
 * ước dự án: mỗi service tự có bản sao công thức/nhãn nhỏ dùng riêng, thay vì tiêm chéo
 * service khác chỉ để tái dùng 1 mảng hằng số.
 */
class ScheduleService
{
    private const ATTENDANCE_LABELS = [
        'present' => ['Có mặt', 'success'],
        'absent' => ['Vắng', 'danger'],
        'excused' => ['Vắng có phép', 'warning'],
        'late' => ['Đi trễ', 'warning'],
    ];

    private const WEEKDAY_LABELS = ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ Nhật'];

    /** Chặn cuộn quá xa 2 hướng cho gọn giao diện (không phải giới hạn bảo mật) — ~1 năm. */
    private const MAX_WEEK_OFFSET = 52;

    public function __construct(
        private readonly ClassEnrollmentRepositoryInterface $classEnrollments,
        private readonly ClassSessionRepositoryInterface $classSessions,
        private readonly AttendanceRepositoryInterface $attendance,
    ) {}

    /** @return array{weekOffset:int, weekStart:Carbon, weekEnd:Carbon, days:array, hasAnyClass:bool, totalSessions:int} */
    public function buildWeekData(User $user, int $weekOffset): array
    {
        $weekOffset = max(-self::MAX_WEEK_OFFSET, min(self::MAX_WEEK_OFFSET, $weekOffset));

        $weekStart = now()->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $classRoomIds = $this->classEnrollments->activeClassRoomIdsForUser($user->id);

        $sessions = $classRoomIds === []
            ? new Collection()
            : $this->classSessions->forClassRoomIdsBetween($classRoomIds, $weekStart, $weekEnd);

        $attendanceBySessionId = $this->attendance->forStudentInSessionIds($user->id, $sessions->pluck('id')->all());

        $days = collect(range(0, 6))->map(function (int $i) use ($weekStart, $sessions, $attendanceBySessionId) {
            $date = $weekStart->copy()->addDays($i);

            $sessionsForDay = $sessions
                ->filter(fn (ClassSession $s) => $s->starts_at !== null && $s->starts_at->isSameDay($date))
                ->sortBy('starts_at')
                ->map(fn (ClassSession $s) => $this->mapSession($s, $attendanceBySessionId))
                ->values()
                ->all();

            return [
                'label' => self::WEEKDAY_LABELS[$i],
                'date' => $date,
                'isToday' => $date->isToday(),
                'sessions' => $sessionsForDay,
            ];
        })->all();

        return [
            'weekOffset' => $weekOffset,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'hasAnyClass' => $classRoomIds !== [],
            'totalSessions' => $sessions->count(),
        ];
    }

    private function mapSession(ClassSession $session, Collection $attendanceBySessionId): array
    {
        [$timeStatusLabel, $timeStatusTone] = $this->timeStatus($session);

        $record = $attendanceBySessionId->get($session->id);
        [$attendanceLabel, $attendanceTone] = $record !== null
            ? (self::ATTENDANCE_LABELS[$record->status->value] ?? [$record->status->value, 'neutral'])
            : ['Chưa điểm danh', 'neutral'];

        return [
            'id' => $session->id,
            'classRoomId' => $session->class_room_id,
            'className' => $session->classRoom->name ?? '',
            'topic' => $session->topic,
            'location' => $session->location,
            'startsAt' => $session->starts_at,
            'endsAt' => $session->ends_at,
            'timeRangeLabel' => $this->timeRangeLabel($session),
            'timeStatusLabel' => $timeStatusLabel,
            'timeStatusTone' => $timeStatusTone,
            'attendanceLabel' => $attendanceLabel,
            'attendanceTone' => $attendanceTone,
        ];
    }

    /** "08:00 - 09:30" — chỉ giờ:phút vì ngày đã thể hiện qua cột/ô của lưới tuần. */
    private function timeRangeLabel(ClassSession $session): string
    {
        if ($session->starts_at === null) {
            return '—';
        }

        if ($session->ends_at === null) {
            return $session->starts_at->format('H:i');
        }

        return $session->starts_at->format('H:i').' - '.$session->ends_at->format('H:i');
    }

    /**
     * Cùng logic với App\Services\Student\ClassRoomService::timeStatus() /
     * App\Services\Teacher\ScheduleService::timeStatus() (giữ nhất quán 1 cách tính trạng
     * thái buổi học trong toàn hệ thống): so theo giờ THỰC hiện tại, đủ 3 mức.
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
}
