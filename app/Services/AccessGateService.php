<?php

namespace App\Services;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Enums\ClassMaterialStatus;
use App\Enums\ProgressUnitType;
use App\Enums\Visibility;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProgressUnlock;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessDecision;

/**
 * Bộ máy trung tâm quyết định "học sinh có được đọc/làm một học liệu không".
 * Cài đúng 7.1 (phạm vi nội dung) và 7.3 (ba cửa độc lập với học liệu riêng tư
 * gắn lớp). Mọi controller/Blade/API đều PHẢI đi qua service này — không kiểm
 * tra quyền rải rác ở nhiều nơi, để khi luật đổi chỉ cần sửa một chỗ (đúng tinh
 * thần "vững chắc, dễ bảo trì" mà anh Phú yêu cầu).
 *
 * Không tin client (16 mục 3): mọi so sánh thời hạn dùng giờ máy chủ (now()).
 */
class AccessGateService
{
    public function canAccessMaterial(User $user, Material $material, ?ClassRoom $classRoom = null): AccessDecision
    {
        $product = $material->product;

        if ($product->visibility === Visibility::Public) {
            return AccessDecision::allow();
        }

        return $classRoom
            ? $this->canAccessViaClass($user, $material, $classRoom)
            : $this->canAccessViaPersonalEntitlement($user, $material);
    }

    /**
     * SỬA 27/8 ("4 file đính kèm sản phẩm" — PDF hướng dẫn/ZIP bài tập/học liệu media): các
     * tài nguyên này gắn THẲNG vào Product (không chia chương/mục, không gắn lớp như Material)
     * nên chỉ cần 1 cửa — sản phẩm công khai HOẶC user có quyền còn hiệu lực. Dùng lại đúng
     * hasActivePersonalAccess() bên dưới, không viết luật riêng.
     */
    public function canAccessProduct(User $user, Product $product): AccessDecision
    {
        if ($product->visibility === Visibility::Public) {
            return AccessDecision::allow();
        }

        if ($this->hasActivePersonalAccess($user, $product->id)) {
            return AccessDecision::allow();
        }

        return AccessDecision::deny(
            reasonCode: 'need_personal_access',
            message: 'Bạn cần quyền học liệu để tải/xem tài nguyên này.',
            ctaLabel: 'Mua học liệu / Nhập mã',
            ctaAction: 'purchase_or_activate',
        );
    }

    /** Truy cập trực tiếp theo sản phẩm (không qua lớp) — 7.1 dòng "Theo sản phẩm". */
    private function canAccessViaPersonalEntitlement(User $user, Material $material): AccessDecision
    {
        if ($this->hasActivePersonalAccess($user, $material->product_id)) {
            return AccessDecision::allow();
        }

        return AccessDecision::deny(
            reasonCode: 'need_personal_access',
            message: 'Bạn cần quyền học liệu để mở bài.',
            ctaLabel: 'Mua học liệu / Nhập mã',
            ctaAction: 'purchase_or_activate',
        );
    }

    /** Truy cập qua lớp — ba cửa độc lập của 7.3. */
    private function canAccessViaClass(User $user, Material $material, ClassRoom $classRoom): AccessDecision
    {
        // Cửa 1: thành viên lớp + học liệu còn gắn lớp.
        $isMember = $classRoom->students()->where('users.id', $user->id)->exists();
        $classMaterial = $classRoom->classMaterials()
            ->where('material_id', $material->id)
            ->first();

        if (! $isMember || ! $classMaterial || $classMaterial->status !== ClassMaterialStatus::Active) {
            return AccessDecision::deny(
                reasonCode: 'not_in_class_path',
                message: 'Bài này không có trong lộ trình lớp của bạn.',
                ctaLabel: 'Xem khóa/lớp phù hợp',
                ctaAction: 'browse_courses',
            );
        }

        $hasPersonalAccess = $this->hasActivePersonalAccess($user, $material->product_id);
        $progressOpen = $this->isProgressOpen($classRoom, $material);

        if (! $hasPersonalAccess && ! $progressOpen) {
            // 7.3: "UI ưu tiên giải thích quyền cá nhân nhưng vẫn thông báo bài sẽ theo lịch mở
            // của lớp sau khi có quyền" — trả cả 2 lý do, primary là quyền cá nhân.
            return AccessDecision::deny(
                reasonCode: 'need_personal_access',
                message: 'Bạn cần quyền học liệu để mở bài. Giáo viên cũng chưa mở nội dung này theo tiến độ lớp.',
                ctaLabel: 'Mua học liệu / Nhập mã',
                ctaAction: 'purchase_or_activate',
                missingGates: ['need_personal_access', 'teacher_not_opened'],
            );
        }

        if (! $hasPersonalAccess) {
            return AccessDecision::deny(
                reasonCode: 'need_personal_access',
                message: 'Bạn cần quyền học liệu để mở bài.',
                ctaLabel: 'Mua học liệu / Nhập mã',
                ctaAction: 'purchase_or_activate',
            );
        }

        if (! $progressOpen) {
            return AccessDecision::deny(
                reasonCode: 'teacher_not_opened',
                message: 'Giáo viên chưa mở nội dung này.',
                ctaLabel: 'Xem lộ trình lớp',
                ctaAction: 'view_class_roadmap',
            );
        }

        return AccessDecision::allow();
    }

    /**
     * Cổng kiểm tra "có phải thành viên của lớp" cho các trang tổng quan lớp
     * (khác canAccessMaterial() ở trên, vốn xét một học liệu cụ thể). Thay cho
     * điều kiện $isMember từng viết rời trong Student\ClassRoomController.
     */
    public function canAccessClassRoom(User $user, ClassRoom $classRoom): AccessDecision
    {
        $isMember = $classRoom->enrollments()
                ->where('student_id', $user->id)
                ->where('status', 'active')
                ->exists()
            || $classRoom->isTaughtBy($user)
            || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN);

        if (! $isMember) {
            return AccessDecision::deny(
                reasonCode: 'not_class_member',
                message: 'Bạn không phải thành viên của lớp này.',
                ctaLabel: 'Xem khóa/lớp phù hợp',
                ctaAction: 'browse_courses',
            );
        }

        return AccessDecision::allow();
    }

    /**
     * SỬA 27/8 ("giáo viên mua tài liệu xong đọc bị 403"): TRƯỚC ĐÂY chỉ nhận
     * scope=PersonalLearning — quyền mua với scope=TeacherTeaching (giá "để dạy", xem
     * ProductService::store()/AccessService::placeOrder()) không bao giờ qua được cửa này, nên
     * giáo viên tự đọc tài liệu mình đã mua (kể cả KHÔNG qua lớp) luôn bị chặn dù quyền còn
     * hiệu lực. Cả 2 scope đều là quyền đọc THẬT đã trả tiền/kích hoạt cho đúng sản phẩm này —
     * khác nhau ở MỤC ĐÍCH dùng (tự học hay dùng để dạy/gắn lớp), không phải ở việc có được mở
     * bài hay không — nên chỗ này chỉ cần "có quyền còn hiệu lực", không lọc theo scope.
     */
    public function hasActivePersonalAccess(User $user, int $productId): bool
    {
        return $user->accessRights()
            ->where('product_id', $productId)
            ->whereIn('scope', [AccessScope::PersonalLearning->value, AccessScope::TeacherTeaching->value])
            ->where('status', AccessRightStatus::Active)
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function isProgressOpen(ClassRoom $classRoom, Material $material): bool
    {
        // Mở theo chương, mục hoặc mã bài (8.2) — coi material chính nó HOẶC material cha
        // (chương/mục chứa nó) đã được mở là đủ.
        $unitIds = array_filter([$material->id, $material->parent_id]);

        return ProgressUnlock::query()
            ->where('class_room_id', $classRoom->id)
            ->whereIn('unit_type', [ProgressUnitType::Chapter->value, ProgressUnitType::Section->value])
            ->whereIn('unit_id', $unitIds)
            ->whereNull('closed_at')
            ->exists();
    }
}
