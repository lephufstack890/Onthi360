<?php

namespace App\Services\Admin;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Models\AccessRight;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Validation\ValidationException;

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

    /** admin.access-rights.create — dữ liệu tĩnh + danh sách sản phẩm để chọn khi cấp quyền. */
    public function createFormData(): array
    {
        return [
            'products' => $this->products->query()->orderBy('title')->get(['id', 'title', 'duration_months'])->all(),
            'scopes' => [
                AccessScope::PersonalLearning->value => 'Học cá nhân',
                AccessScope::TeacherTeaching->value => 'Dùng để dạy (mọi lớp phụ trách, không giới hạn)',
            ],
        ];
    }

    /**
     * admin.access-rights.store — Admin CẤP QUYỀN TRỰC TIẾP (source=admin_grant), KHÔNG đi
     * qua OrderActivationService — đây là 1 nguồn hợp lệ riêng theo schema (cột "source" cho
     * phép order|gift|admin_grant|package, xem migration access_rights), không vi phạm bất
     * biến "AccessRight chỉ tạo ở OrderActivationService::activate()" (bất biến đó chỉ áp
     * dụng CHO LUỒNG ĐƠN HÀNG — xem docs/ARCHITECTURE.md mục 4). starts_at = thời điểm cấp
     * (giống ý nghĩa "kích hoạt" của 7.4: thời hạn bắt đầu từ lúc quyền thực sự có hiệu lực).
     * PHẢI có lý do + audit log (10.4) — Auditable đã gắn sẵn ở AccessRight model.
     */
    public function grant(User $admin, array $data, string $reason): AccessRight
    {
        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Không tìm thấy người dùng với email này.']);
        }

        if ($data['scope'] === AccessScope::TeacherTeaching->value && ! $user->isTeacherApproved()) {
            throw ValidationException::withMessages(['email' => 'Quyền "dùng để dạy" chỉ cấp được cho giáo viên đã được Admin duyệt (3.3, 7.2).']);
        }

        $product = $this->products->findOrFail($data['product_id']);
        $startsAt = now();
        $expiresAt = filled($data['expires_at'] ?? null)
            ? \Illuminate\Support\Carbon::parse($data['expires_at'])->endOfDay()
            : ($product->duration_months ? $startsAt->copy()->addMonths($product->duration_months) : null);

        AccessRight::$auditReason = $reason;
        $accessRight = $this->accessRights->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'scope' => $data['scope'],
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => AccessRightStatus::Active->value,
            // class_limit LUÔN null — kể cả scope=teacher_teaching (bất biến 5.3/7.2: unlimited).
            'class_limit' => null,
            'source' => 'admin_grant',
            'source_id' => null,
            'created_by' => $admin->id,
        ]);
        AccessRight::$auditReason = null;

        return $accessRight;
    }

    /** admin.access-rights.show — chi tiết 1 quyền để xem/thu hồi. */
    public function showData(int $id): array
    {
        /** @var AccessRight $right */
        $right = $this->accessRights->query()->with(['user', 'product'])->findOrFail($id);
        [$statusLabel, $tone] = $this->expiryStatus($right);

        return ['right' => $right, 'statusLabel' => $statusLabel, 'tone' => $tone];
    }

    /** admin.access-rights.revoke — PHẢI có lý do + audit log (10.4). */
    public function revoke(AccessRight $accessRight, string $reason): AccessRight
    {
        AccessRight::$auditReason = $reason;
        $this->accessRights->update($accessRight, ['status' => AccessRightStatus::Revoked->value]);
        AccessRight::$auditReason = null;

        return $accessRight;
    }
}