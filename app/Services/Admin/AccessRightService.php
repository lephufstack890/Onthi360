<?php

namespace App\Services\Admin;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;

/**
 * Gom truy vấn/nhãn cho admin.access-rights.index — 7.1-7.5: quyền học cá nhân /
 * quyền dạy đa lớp.
 */
class AccessRightService
{
    public function __construct(
        private AccessRightRepositoryInterface $accessRights,
        private ProductRepositoryInterface $products,
    ) {}

    /** @return array{tabs: array, rights: array} */
    public function indexData(): array
    {
        $tabs = [
            ['label' => 'Sản phẩm', 'href' => route('admin.products.index'), 'active' => false, 'count' => $this->products->count()],
            ['label' => 'Quyền đã cấp', 'href' => route('admin.access-rights.index'), 'active' => true, 'count' => $this->accessRights->count()],
        ];

        $rights = $this->accessRights->latestWithUserAndProduct(50)->map(function ($r) {
            [$statusLabel, $tone] = $this->expiryStatus($r);

            return [
                'id' => $r->id,
                'user' => $r->user->name ?? '',
                'product' => $r->product->title ?? '',
                'scope' => $r->scope === AccessScope::TeacherTeaching ? 'Dùng để dạy (mọi lớp phụ trách)' : 'Học cá nhân',
                'expires' => $r->expires_at?->format('d/m/Y') ?? 'Không xác định',
                'status' => $statusLabel,
                'tone' => $tone,
            ];
        })->all();

        return ['tabs' => $tabs, 'rights' => $rights];
    }

    /** Phân loại cửa sổ hết hạn cho 1 AccessRight — trả về [nhãn, tone]. */
    private function expiryStatus(\App\Models\AccessRight $r): array
    {
        return match (true) {
            $r->status === AccessRightStatus::Active && $r->expires_at?->isFuture() && $r->expires_at->diffInDays(now()) <= 14 => ['Sắp hết hạn', 'warning'],
            $r->status === AccessRightStatus::Active => ['Hiệu lực', 'success'],
            $r->status === AccessRightStatus::Expired => ['Hết hạn', 'danger'],
            default => [(string) $r->status->value, 'neutral'],
        };
    }
}
