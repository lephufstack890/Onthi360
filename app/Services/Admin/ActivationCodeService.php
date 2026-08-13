<?php

namespace App\Services\Admin;

use App\Enums\AccessScope;
use App\Enums\ActivationCodeStatus;
use App\Models\ActivationCode;
use App\Repositories\Contracts\ActivationCodeRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Gom truy vấn/nhãn cho admin.activation-codes.index — 7.4: mã sai scope không tự chuyển đổi.
 */
class ActivationCodeService
{
    public function __construct(private ActivationCodeRepositoryInterface $activationCodes) {}

    /** @return array{codes: array} */
    public function indexData(): array
    {
        $codes = $this->activationCodes->latestWithOrderItemOrder(50)->map(fn ($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'order' => $c->orderItem->order->id ?? null,
            'scope' => $c->scope === AccessScope::TeacherTeaching ? 'Dùng để dạy' : 'Học cá nhân',
            'status' => match ($c->status) {
                ActivationCodeStatus::Unused => 'Chưa dùng',
                ActivationCodeStatus::Activated => 'Đã dùng',
                ActivationCodeStatus::Revoked => 'Đã thu hồi',
            },
            'tone' => match ($c->status) {
                ActivationCodeStatus::Unused => 'neutral',
                ActivationCodeStatus::Activated => 'success',
                ActivationCodeStatus::Revoked => 'danger',
            },
            'canRevoke' => $c->status === ActivationCodeStatus::Unused,
        ])->all();

        return ['codes' => $codes];
    }

    /**
     * admin.activation-codes.revoke — chỉ thu hồi được mã CHƯA dùng (unused). Mã đã kích hoạt
     * rồi (đã tạo AccessRight) muốn thu quyền phải thu hồi ở chính AccessRight đó
     * (admin.access-rights.revoke) — thu hồi mã ở đây không tự động thu quyền đã cấp.
     * PHẢI có lý do + audit log (10.4).
     */
    public function revoke(ActivationCode $code, string $reason): ActivationCode
    {
        if ($code->status !== ActivationCodeStatus::Unused) {
            throw ValidationException::withMessages(['status' => 'Chỉ thu hồi được mã chưa dùng.']);
        }

        ActivationCode::$auditReason = $reason;
        $code->update(['status' => ActivationCodeStatus::Revoked]);
        ActivationCode::$auditReason = null;

        return $code;
    }
}
