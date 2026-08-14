<?php

namespace App\Services\Parent;

use App\Enums\ParentLinkStatus;
use App\Models\ParentLink;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Repositories\Contracts\ParentLinkRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Dữ liệu cho parent.children.index (danh sách con) và parent.children.show
 * (PAR-02: lịch/điểm danh/kết quả/tiến độ/review của một con).
 */
class ChildService
{
    public function __construct(
        private ParentLinkRepositoryInterface $parentLinks,
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private AttemptRepositoryInterface $attempts,
        private AttendanceRepositoryInterface $attendances,
        private ClassSessionRepositoryInterface $classSessions,
        private UserRepositoryInterface $users,
    ) {}

    /** parent.children.index — chỉ hiển thị học sinh đã liên kết, không cho tìm kiếm học sinh khác (10.3, 3.3). */
    public function listForParent(User $user): array
    {
        $links = $this->parentLinks->forParentWithStudent($user->id);
        $studentIds = $links->map(fn ($link) => $link->student->id)->all();
        $enrollmentsByStudent = $this->activeEnrollmentsByStudent($studentIds);

        $children = $links->map(function (ParentLink $link) use ($enrollmentsByStudent) {
            $child = $link->student;
            $classRoom = $enrollmentsByStudent->get($child->id)?->classRoom;

            return [
                'id' => $child->id,
                'name' => $child->name,
                'class' => $classRoom->name ?? 'Chưa có lớp',
                'status' => $link->isVerified() ? 'Đã xác minh' : ($link->status === ParentLinkStatus::Pending ? 'Chờ xác minh' : 'Đã hủy liên kết'),
                'tone' => $link->isVerified() ? 'success' : ($link->status === ParentLinkStatus::Pending ? 'warning' : 'neutral'),
            ];
        })->all();

        return ['children' => $children];
    }

    /**
     * Con ĐÃ XÁC MINH — dùng làm điểm vào cho các mục điều hướng cấp cao "Lịch & Điểm danh"
     * / "Kết quả & Tiến độ" (chỉ liên kết đã xác minh mới xem được dữ liệu con, 10.3): 1 con
     * thì vào thẳng tab tương ứng của parent.children.show, nhiều con thì hiện danh sách chọn.
     *
     * @return array<int, array{id: int, name: string, class: string}>
     */
    public function verifiedChildrenForParent(User $user): array
    {
        $links = $this->parentLinks->verifiedForParentWithStudent($user->id);
        $studentIds = $links->map(fn ($link) => $link->student->id)->all();
        $enrollmentsByStudent = $this->activeEnrollmentsByStudent($studentIds);

        return $links->map(function (ParentLink $link) use ($enrollmentsByStudent) {
            $child = $link->student;
            $classRoom = $enrollmentsByStudent->get($child->id)?->classRoom;

            return [
                'id' => $child->id,
                'name' => $child->name,
                'class' => $classRoom->name ?? 'Chưa có lớp',
            ];
        })->values()->all();
    }

    /**
     * "Nhập email của con" (STU-11 header/PAR-đ. mới) — tạo yêu cầu liên kết (ParentLink
     * status=pending), chờ admin xác minh (10.3: "xác minh phụ huynh chặt chẽ") — trước đây
     * đây chỉ là 1 ô nhập "mã liên kết" trang trí, không có mã/token nào thật trong schema
     * (parent_links không có cột mã), nên đổi sang định danh thật: EMAIL của học sinh, giống
     * cách admin tạo/tìm user theo email ở nơi khác trong hệ thống.
     *
     * @throws ValidationException nếu không tìm thấy học sinh, hoặc đã có liên kết (verified/
     *                              pending/revoked) với đúng học sinh này — unique constraint
     *                              (parent_user_id, student_user_id) của bảng cũng chặn trùng.
     */
    public function requestLink(User $parent, string $studentEmail): ParentLink
    {
        $student = $this->users->findByEmail($studentEmail);

        if ($student === null || ! $student->hasRole(Role::STUDENT)) {
            throw ValidationException::withMessages([
                'student_email' => 'Không tìm thấy học sinh với email này.',
            ]);
        }

        $existing = $this->parentLinks->query()
            ->where('parent_user_id', $parent->id)
            ->where('student_user_id', $student->id)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'student_email' => match ($existing->status->value) {
                    'verified' => 'Bạn đã liên kết và xác minh với học sinh này rồi.',
                    'pending' => 'Yêu cầu liên kết với học sinh này đang chờ admin xác minh.',
                    default => 'Liên kết này đã bị huỷ trước đó — liên hệ admin để được hỗ trợ lại.',
                },
            ]);
        }

        return $this->parentLinks->create([
            'parent_user_id' => $parent->id,
            'student_user_id' => $student->id,
            'status' => ParentLinkStatus::Pending->value,
            'verification_method' => 'parent_request',
        ]);
    }

    /**
     * parent.children.show (PAR-02).
     *
     * findVerifiedLink() LÀ cửa xác quyền: chỉ liên kết đã được xác minh mới
     * cho phụ huynh xem dữ liệu con — không được nới lỏng khi refactor (10.3).
     */
    public function showForParent(User $user, int $childId, string $tab): array
    {
        $link = $this->parentLinks->findVerifiedLink($user->id, $childId);

        if (! $link) {
            throw (new ModelNotFoundException())->setModel(ParentLink::class);
        }

        $childUser = $link->student;
        $enrollment = $this->classEnrollments->activeForUser($childUser->id, ['classRoom.course'])->first();
        $classRoom = $enrollment?->classRoom;

        $tabDefs = [
            'overview' => 'Tổng quan',
            'schedule' => 'Lịch & Điểm danh',
            'results' => 'Kết quả & Tiến độ',
            'review' => 'Đánh giá lớp',
        ];
        $tabsData = [];
        foreach ($tabDefs as $key => $label) {
            $tabsData[] = [
                'label' => $label,
                'href' => route('parent.children.show', ['child' => $childUser->id, 'tab' => $key]),
                'active' => $tab === $key,
            ];
        }

        $results = [];
        if ($tab === 'results' || $tab === 'overview') {
            $results = $this->attempts->recentSubmittedForUser($childUser->id, 10)
                ->map(fn ($a) => [
                    'title' => $a->assessment->title ?? 'Bài đã nộp',
                    'score' => $a->total_score !== null ? (string) $a->total_score : 'Đang chấm',
                    'tone' => $a->is_provisional ? 'info' : 'success',
                    'time' => $a->submitted_at?->diffForHumans(),
                ])->all();
        }

        $attendance = [];
        if ($tab === 'schedule' && $classRoom) {
            $attendance = $this->attendances->forStudentInClassRoom($childUser->id, $classRoom->id, 20)
                ->map(fn ($a) => [
                    'date' => $a->classSession->starts_at?->format('d/m/Y') ?? '',
                    'status' => match ($a->status->value) {
                        'present' => 'Có mặt',
                        'late' => 'Đi muộn',
                        'excused' => 'Vắng có phép',
                        'absent' => 'Vắng không phép',
                        default => $a->status->value,
                    },
                    'tone' => match ($a->status->value) {
                        'present' => 'success',
                        'late' => 'info',
                        'excused' => 'warning',
                        'absent' => 'danger',
                        default => 'neutral',
                    },
                ])->all();
        }

        $nextSession = $classRoom ? $this->classSessions->nextUpcomingForClassRoom($classRoom->id) : null;

        return [
            'child' => $childUser,
            'classRoom' => $classRoom,
            'tab' => $tab,
            'tabsData' => $tabsData,
            'results' => $results,
            'attendance' => $attendance,
            'nextSession' => $nextSession,
        ];
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
}
