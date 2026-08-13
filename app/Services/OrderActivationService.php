<?php

namespace App\Services;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Enums\ActivationCodeStatus;
use App\Enums\OrderStatus;
use App\Models\ActivationCode;
use App\Models\AccessRight;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\AccessDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cài đúng vòng đời 7.4: Đặt đơn -> Thanh toán -> Duyệt -> Cấp mã -> Kích hoạt
 * -> Quyền bắt đầu hiệu lực. Bất biến quan trọng nhất: KHÔNG có AccessRight nào
 * được tạo trước bước activate() — "Tạo đơn ≠ đã thanh toán ≠ đã có quyền".
 */
class OrderActivationService
{
    /** Admin duyệt đơn thanh toán ngoài hệ thống -> sinh mã kích hoạt cho từng item. */
    public function approveOfflineOrder(Order $order, User $admin): Order
    {
        return DB::transaction(function () use ($order, $admin) {
            $order->update([
                'status' => OrderStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            $order->items->each(function (OrderItem $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    ActivationCode::create([
                        'code' => $this->generateUniqueCode(),
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'scope' => $item->scope,
                        'status' => ActivationCodeStatus::Unused,
                        'validity_months' => $item->product->duration_months,
                    ]);
                }
            });

            $order->update(['status' => OrderStatus::Completed]);

            return $order->refresh();
        });
    }

    /** Admin từ chối — PHẢI có lý do, ghi audit log (App\Concerns\Auditable đọc Order::$auditReason). */
    public function rejectOrder(Order $order, User $admin, string $reason): Order
    {
        Order::$auditReason = $reason;
        $order->update([
            'status' => OrderStatus::Rejected,
            'approved_by' => $admin->id,
            'rejected_reason' => $reason,
        ]);
        Order::$auditReason = null;

        return $order;
    }

    public function canActivate(ActivationCode $code, User $user): AccessDecision
    {
        if ($code->status !== ActivationCodeStatus::Unused) {
            return AccessDecision::deny('code_not_usable', 'Mã kích hoạt không hợp lệ hoặc đã được sử dụng.');
        }

        // Mã quyền dạy chỉ kích hoạt được cho giáo viên đã duyệt — không tự chuyển thành
        // quyền học sinh (7.4: "Mã kích hoạt sai scope không được chuyển đổi tự động").
        if ($code->scope === AccessScope::TeacherTeaching && ! $user->isTeacherApproved()) {
            return AccessDecision::deny(
                'must_be_approved_teacher',
                'Mã này cấp quyền dùng để dạy — chỉ giáo viên đã được duyệt mới kích hoạt được.',
            );
        }

        return AccessDecision::allow();
    }

    /** Thời hạn BẮT ĐẦU từ lúc kích hoạt hợp lệ, không phải lúc đặt đơn/duyệt (7.4). */
    public function activate(ActivationCode $code, User $user): AccessRight
    {
        $decision = $this->canActivate($code, $user);

        if (! $decision->allowed) {
            throw new \RuntimeException("Không thể kích hoạt mã: {$decision->primaryReasonCode}");
        }

        return DB::transaction(function () use ($code, $user) {
            $startsAt = now();
            $expiresAt = $code->validity_months
                ? $startsAt->copy()->addMonths($code->validity_months)
                : null;

            $accessRight = AccessRight::create([
                'user_id' => $user->id,
                'product_id' => $code->product_id,
                'scope' => $code->scope,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => AccessRightStatus::Active,
                // class_limit luôn null: với scope=teacher_teaching, null BẮT BUỘC nghĩa là
                // "unlimited" (5.3/7.2); với scope=personal_learning, cột này không áp dụng.
                'class_limit' => null,
                'source' => 'order',
                'source_id' => $code->order_item_id,
                'created_by' => $user->id,
            ]);

            $code->update([
                'status' => ActivationCodeStatus::Activated,
                'activated_by' => $user->id,
                'activated_at' => $startsAt,
            ]);

            return $accessRight;
        });
    }

    private function generateUniqueCode(): string
    {
        do {
            $candidate = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
        } while (ActivationCode::where('code', $candidate)->exists());

        return $candidate;
    }
}
