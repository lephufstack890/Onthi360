<?php

namespace App\Services\Admin;

use App\Repositories\Contracts\TeacherProfileRepositoryInterface;

/**
 * Gom truy vấn cho hàng đợi duyệt giáo viên (admin.teacher-approvals.*, 3.3).
 *
 * Không có approve()/reject() ở đây: TeacherApprovalController hiện chỉ có
 * index/show, không có action nào để gắn App\Services\OrderActivationService
 * hay một service duyệt/từ chối riêng vào — việc thêm route/method đó là một
 * tính năng khác, ngoài phạm vi refactor này.
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

    /** @return array{profile: \App\Models\TeacherProfile, documents: array} */
    public function showData(int $teacherProfileId): array
    {
        $profile = $this->teacherProfiles->query()->with('user')->findOrFail($teacherProfileId);

        return [
            'profile' => $profile,
            // TODO: chưa có bảng tài liệu minh chứng (CMND/bằng cấp) trong schema hiện tại.
            'documents' => [],
        ];
    }
}
