<?php

namespace App\Services\Admin;

use App\Enums\TeacherApprovalStatus;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;

/**
 * Gom truy vấn + hành động cho hàng đợi duyệt giáo viên (admin.teacher-approvals.*, 3.3).
 *
 * State machine 3.3: Chưa đăng ký -> Chờ duyệt -> Đã được duyệt / Từ chối có lý do;
 * Đã được duyệt -> Tạm dừng (thu hồi quyền mới, không xoá lịch sử) -> Duyệt lại.
 * Từ chối/Tạm dừng PHẢI có lý do (16 mục 4) — App\Concerns\Auditable tự ghi audit log
 * khi update(), lý do được đọc từ TeacherProfile::$auditReason.
 */
class TeacherApprovalService
{
    public function __construct(private TeacherProfileRepositoryInterface $teacherProfiles) {}

    /** @return array{pending: array} */
    public function pendingQueue(): array
    {
        $pending = $this->teacherProfiles->pendingWithUser()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->user->name ?? '',
            'email' => $p->user->email ?? '',
            'submitted' => $p->created_at?->format('d/m/Y'),
            // TODO: chưa có trường "môn/chuyên môn" tách riêng — dùng subjects[0] nếu có.
            'subject' => is_array($p->subjects) && count($p->subjects) > 0 ? $p->subjects[0] : '',
        ])->all();

        return ['pending' => $pending];
    }

    /** @return array{profile: TeacherProfile, documents: array} */
    public function showData(int $teacherProfileId): array
    {
        $profile = $this->teacherProfiles->query()->with(['user', 'approver'])->findOrFail($teacherProfileId);

        return [
            'profile' => $profile,
            // TODO: chưa có bảng tài liệu minh chứng (CMND/bằng cấp) trong schema hiện tại.
            'documents' => [],
        ];
    }

    /** Chờ duyệt/Từ chối/Tạm dừng -> Đã được duyệt. Không yêu cầu lý do. */
    public function approve(TeacherProfile $profile, User $admin): TeacherProfile
    {
        $profile->update([
            'approval_status' => TeacherApprovalStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return $profile;
    }

    /** Chờ duyệt -> Từ chối có lý do (16 mục 4: phải ghi lý do). */
    public function reject(TeacherProfile $profile, User $admin, string $reason): TeacherProfile
    {
        TeacherProfile::$auditReason = $reason;

        $profile->update([
            'approval_status' => TeacherApprovalStatus::Rejected,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
            // Bị từ chối thì không thể còn hiển thị ở trang vinh danh.
            'is_featured' => false,
        ]);

        TeacherProfile::$auditReason = null;

        return $profile;
    }

    /**
     * Đã được duyệt -> Tạm dừng (3.3: "quyền mới không được tạo; lớp/học liệu đang dùng xử
     * lý theo chính sách admin") — CHỈ đổi trạng thái, không tự huỷ AccessRight/ClassMaterial
     * đang có, việc đó thuộc AccessGateService/TeacherAttachMaterialAction ở tầng khác.
     */
    public function suspend(TeacherProfile $profile, User $admin, string $reason): TeacherProfile
    {
        TeacherProfile::$auditReason = $reason;

        $profile->update([
            'approval_status' => TeacherApprovalStatus::Suspended,
            'rejection_reason' => $reason,
            'is_featured' => false,
        ]);

        TeacherProfile::$auditReason = null;

        return $profile;
    }

    /** Tạm dừng/Từ chối -> Đã được duyệt lại. */
    public function reinstate(TeacherProfile $profile, User $admin): TeacherProfile
    {
        return $this->approve($profile, $admin);
    }
}
