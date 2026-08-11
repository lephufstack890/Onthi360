<?php

namespace App\Services;

use App\Enums\AccessScope;
use App\Enums\ReviewTargetType;
use App\Models\ClassRoom;
use App\Models\Product;
use App\Models\User;
use App\Support\AccessDecision;

/**
 * Cài đúng 9.2 "Ai được đánh giá và khi nào". Trả AccessDecision (không phải
 * bool) vì màn hình cần hiển thị đúng lý do còn thiếu ("mở tài liệu" hay
 * "tham gia 2 buổi" — yêu cầu tại 9.6 mục 4).
 */
class ReviewEligibilityService
{
    public function eligibleForMaterialReview(User $user, Product $product): AccessDecision
    {
        $isTeacher = $user->isTeacherApproved();

        if ($isTeacher) {
            $hasAttached = \App\Models\ClassMaterial::where('product_id', $product->id)
                ->where('added_by', $user->id)
                ->exists();

            return $hasAttached
                ? AccessDecision::allow()
                : AccessDecision::deny('teacher_not_attached', 'Bạn chưa gắn học liệu này vào lớp nào.');
        }

        $hasAccess = $user->accessRights()
            ->where('product_id', $product->id)
            ->where('scope', AccessScope::PersonalLearning)
            ->exists(); // còn HOẶC hết hạn đều tính là "đã từng có quyền" (9.2)

        if (! $hasAccess) {
            return AccessDecision::deny('no_entitlement', 'Bạn cần có quyền học liệu này trước.');
        }

        $hasOpenedOrAttempted = \App\Models\Attempt::where('user_id', $user->id)
            ->whereHas('assessment.materials', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        return $hasOpenedOrAttempted
            ? AccessDecision::allow()
            : AccessDecision::deny('not_opened_yet', 'Bạn cần mở ít nhất một phần tài liệu trước khi đánh giá.');
    }

    public function eligibleForClassReview(User $user, ClassRoom $classRoom): AccessDecision
    {
        if ($classRoom->isTaughtBy($user)) {
            return AccessDecision::deny('teacher_cannot_review_own_class', 'Giáo viên không tự đánh giá lớp mình dạy.');
        }

        $isParent = $user->childLinks()->where('status', 'verified')->exists();

        if ($isParent) {
            $childIds = $user->childLinks()->where('status', 'verified')->pluck('student_user_id');
            $anyChildEligible = $childIds->contains(
                fn ($childId) => $this->studentMeetsClassThreshold($classRoom, $childId)
            );

            return $anyChildEligible
                ? AccessDecision::allow()
                : AccessDecision::deny('child_not_eligible_yet', 'Con của bạn chưa đủ điều kiện trải nghiệm lớp này.');
        }

        $enrollment = $classRoom->enrollments()->where('student_id', $user->id)->first();

        if (! $enrollment) {
            return AccessDecision::deny('not_a_member', 'Bạn không phải thành viên của lớp này.');
        }

        // Rời lớp vẫn còn 14 ngày để phản hồi (9.2).
        if ($enrollment->status === 'left' && $enrollment->left_at?->addDays(14)->isPast()) {
            return AccessDecision::deny('feedback_window_closed', 'Đã quá 14 ngày kể từ khi rời lớp.');
        }

        return $this->studentMeetsClassThreshold($classRoom, $user->id)
            ? AccessDecision::allow()
            : AccessDecision::deny('threshold_not_met', 'Bạn cần tham gia ít nhất 2 buổi hoặc hoàn thành một hoạt động.');
    }

    private function studentMeetsClassThreshold(ClassRoom $classRoom, int $studentId): bool
    {
        $attendanceCount = \App\Models\Attendance::where('student_id', $studentId)
            ->where('status', 'present')
            ->whereIn('class_session_id', $classRoom->sessions()->pluck('id'))
            ->count();

        if ($attendanceCount >= 2) {
            return true;
        }

        return \App\Models\Attempt::where('user_id', $studentId)
            ->where('class_room_id', $classRoom->id)
            ->where('status', 'graded')
            ->exists();
    }
}
