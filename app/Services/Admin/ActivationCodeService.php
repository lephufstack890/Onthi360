<?php

namespace App\Services\Admin;

use App\Enums\AccessScope;
use App\Enums\ActivationCodeStatus;
use App\Repositories\Contracts\ActivationCodeRepositoryInterface;

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
        ])->all();

        return ['codes' => $codes];
    }
}
