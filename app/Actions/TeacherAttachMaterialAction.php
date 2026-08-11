<?php

namespace App\Actions;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Enums\ClassMaterialStatus;
use App\Models\ClassMaterial;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\User;
use App\Support\AccessDecision;
use Illuminate\Support\Facades\DB;

/**
 * Cài đúng bảng 7.2 "Điều kiện khi giáo viên thêm học liệu vào lớp". Đây là
 * hành động nhạy cảm nhất trong toàn hệ thống (nếu sai, học sinh có thể đọc
 * được nội dung riêng tư mà không ai có quyền) nên được tách thành 1 Action
 * độc lập, có test riêng, không lồng trong controller.
 */
class TeacherAttachMaterialAction
{
    public function authorize(User $teacher, Material $material, ClassRoom $classRoom): AccessDecision
    {
        if (! $teacher->isTeacherApproved()) {
            return AccessDecision::deny(
                reasonCode: 'teacher_not_approved',
                message: 'Bạn cần hoàn tất phê duyệt giáo viên trước khi gắn học liệu.',
                ctaLabel: 'Hoàn tất phê duyệt giáo viên',
                ctaAction: 'complete_teacher_approval',
            );
        }

        $teachingRight = $teacher->accessRights()
            ->where('product_id', $material->product_id)
            ->where('scope', AccessScope::TeacherTeaching)
            ->latest('expires_at')
            ->first();

        if (! $teachingRight) {
            return AccessDecision::deny(
                reasonCode: 'no_teaching_right',
                message: 'Bạn chưa có quyền dùng để dạy cho học liệu này.',
                ctaLabel: 'Mua/kích hoạt quyền dạy',
                ctaAction: 'purchase_teaching_right',
            );
        }

        if ($teachingRight->status !== AccessRightStatus::Active || $teachingRight->expires_at?->isPast()) {
            return AccessDecision::deny(
                reasonCode: 'teaching_right_expired',
                message: 'Quyền dạy cho học liệu này đã hết hạn.',
                ctaLabel: 'Gia hạn quyền dạy',
                ctaAction: 'renew_teaching_right',
            );
        }

        if (! $classRoom->isTaughtBy($teacher)) {
            return AccessDecision::deny(
                reasonCode: 'not_class_teacher',
                message: 'Bạn không phụ trách lớp này.',
                ctaLabel: null,
                ctaAction: null,
            );
        }

        return AccessDecision::allow();
    }

    /**
     * @throws \RuntimeException nếu chưa đủ điều kiện — luôn gọi authorize() trước ở tầng
     *                            controller/policy để hiển thị lý do cho người dùng; exception
     *                            ở đây chỉ là lưới an toàn cuối cùng phía server.
     */
    public function execute(User $teacher, Material $material, ClassRoom $classRoom): ClassMaterial
    {
        $decision = $this->authorize($teacher, $material, $classRoom);

        if (! $decision->allowed) {
            throw new \RuntimeException("Không thể gắn học liệu: {$decision->primaryReasonCode}");
        }

        return DB::transaction(function () use ($teacher, $material, $classRoom) {
            // updateOrCreate: gắn lại học liệu đã "Đã gỡ" trước đó sẽ tái dùng bản ghi cũ
            // thay vì tạo link mới — giữ nguyên lịch sử OJ/version (nguyên tắc 2.2 mục 3).
            return ClassMaterial::query()->updateOrCreate(
                ['class_room_id' => $classRoom->id, 'material_id' => $material->id],
                [
                    'product_id' => $material->product_id,
                    'release_version' => 1,
                    'status' => ClassMaterialStatus::Active,
                    'added_by' => $teacher->id,
                    'added_at' => now(),
                    'removed_at' => null,
                ]
            );
        });
    }

    /** Gỡ học liệu KHÔNG xóa lịch sử — chỉ đổi trạng thái (8.2). */
    public function detach(ClassMaterial $classMaterial): void
    {
        $classMaterial->update([
            'status' => ClassMaterialStatus::Removed,
            'removed_at' => now(),
        ]);
    }
}
