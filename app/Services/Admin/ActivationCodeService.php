<?php

namespace App\Services\Admin;

use App\Enums\AccessScope;
use App\Enums\ActivationCodeStatus;
use App\Models\ActivationCode;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\ActivationCodeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\OrderActivationService;
use Illuminate\Validation\ValidationException;

/**
 * Gom truy vấn/nhãn cho admin.activation-codes.index — 7.4: mã sai scope không tự chuyển đổi.
 */
class ActivationCodeService
{
    public function __construct(
        private ActivationCodeRepositoryInterface $activationCodes,
        // SỬA 18/9 — màn "Cấp mã kích hoạt" cần danh sách tài liệu để chọn, và dùng CHUNG bộ
        // sinh mã của luồng đơn hàng (OrderActivationService) để mã cấp tay và mã sinh từ đơn
        // cùng một định dạng/cùng một cách chống trùng.
        private ProductRepositoryInterface $products,
        private OrderActivationService $orderActivation,
    ) {}

    /** @return array{codes: array} */
    public function indexData(): array
    {
        // SỬA 18/9 — chưa chạy migration 000860 thì cột assigned_user_id chưa có: nạp quan hệ
        // assignedUser sẽ lỗi SQL ngay giữa trang. Báo rõ ở đầu trang rồi hiển thị bảng theo
        // kiểu cũ, thay vì để admin nhìn thấy một trang lỗi không hiểu vì sao.
        $ready = ActivationCode::supportsAssignedUser();

        $codes = $this->activationCodes->latestWithOrderItemOrder(50, $ready)->map(fn ($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'order' => $c->orderItem->order->id ?? null,
            // SỬA 18/9 — 4 cột mới trả lời thẳng câu hỏi "mã này của tài liệu nào, cấp cho ai,
            // ai đã dùng": trước đây bảng chỉ có Mã / Đơn / Phạm vi / Trạng thái.
            'product' => $c->product->title ?? '—',
            'assignedTo' => $ready && $c->assignedUser
                ? $c->assignedUser->name.' ('.$c->assignedUser->email.')'
                : null,
            'activatedBy' => $c->activatedBy
                ? $c->activatedBy->name.' ('.$c->activatedBy->email.')'
                : null,
            'activatedAt' => $c->activated_at?->format('d/m/Y H:i'),
            'note' => $ready ? $c->note : null,
            'validity' => $c->validity_months ? $c->validity_months.' tháng' : 'Vĩnh viễn',
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

        return ['codes' => $codes, 'assignedUserReady' => $ready];
    }

    /**
     * admin.activation-codes.create — dữ liệu dựng biểu mẫu cấp mã.
     *
     * Danh sách tài khoản CỐ Ý chỉ gồm học sinh + giáo viên (khách: "đưa cho tài khoản học sinh
     * hoặc giáo viên để mở") — cấp mã cho chính tài khoản admin/phụ huynh không có ý nghĩa gì.
     */
    public function createFormData(): array
    {
        $this->assertAssignedUserReady();

        return [
            'products' => $this->products->query()->orderBy('title')->get(['id', 'title', 'duration_months'])->all(),
            'users' => User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::STUDENT, Role::TEACHER]))
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    // Hiện rõ vai trò ngay trên dropdown: chọn nhầm tài khoản học sinh cho mã
                    // "Dùng để dạy" là lỗi hay gặp, thấy nhãn thì tránh được từ đầu.
                    'roleLabel' => $u->hasRole(Role::TEACHER) ? 'Giáo viên' : 'Học sinh',
                ])
                ->all(),
            'scopes' => [
                AccessScope::PersonalLearning->value => 'Học cá nhân',
                AccessScope::TeacherTeaching->value => 'Dùng để dạy (giáo viên đã được duyệt)',
            ],
        ];
    }

    /**
     * admin.activation-codes.store — CẤP TAY 1 mã kích hoạt, khoá theo đúng 1 tài khoản.
     *
     * Khác luồng đơn hàng (OrderActivationService::approveOfflineOrder() sinh mã sau khi duyệt
     * đơn): ở đây không có đơn nào cả nên order_item_id = null, bù lại có assigned_user_id để
     * "mã của tài khoản nào thì tài khoản đó mở" — luật này được THI HÀNH ở
     * OrderActivationService::canActivate(), hàm này chỉ ghi dữ liệu.
     *
     * Vẫn giữ đúng bất biến 7.4 "Cấp mã ≠ đã có quyền": hàm này KHÔNG tạo AccessRight nào —
     * quyền chỉ sinh ra khi chính tài khoản đó nhập mã và bấm Kích hoạt (thời hạn cũng tính từ
     * lúc đó). Muốn cấp quyền thẳng không qua mã thì dùng màn "Cấp quyền truy cập"
     * (Admin\AccessRightService::grant()).
     *
     * @param  array{user_id: int|string, product_id: int|string, scope: string, validity_months?: int|string|null, note?: string|null}  $data
     *
     * @throws ValidationException nếu không tìm thấy tài khoản/tài liệu, cấp mã "Dùng để dạy"
     *                              cho tài khoản chưa được duyệt giáo viên, hoặc tài khoản đó
     *                              đang còn 1 mã chưa dùng cho đúng tài liệu + phạm vi này.
     */
    public function store(User $admin, array $data): ActivationCode
    {
        $this->assertAssignedUserReady();

        $user = User::find($data['user_id']);
        if (! $user) {
            throw ValidationException::withMessages(['user_id' => 'Không tìm thấy tài khoản này.']);
        }

        $scope = AccessScope::from($data['scope']);

        // Cùng luật với AccessRightService::grant() và canActivate(): mã quyền dạy không tự
        // chuyển thành quyền học (7.4). Chặn ngay lúc CẤP để admin biết liền, thay vì cấp xong
        // giáo viên nhập mã mới báo lỗi.
        if ($scope === AccessScope::TeacherTeaching && ! $user->isTeacherApproved()) {
            throw ValidationException::withMessages([
                'user_id' => 'Mã "Dùng để dạy" chỉ cấp được cho giáo viên đã được Admin duyệt (3.3, 7.2).',
            ]);
        }

        $product = $this->products->findOrFail($data['product_id']);

        $existing = $this->activationCodes->unusedFor($user->id, $product->id, $scope->value);
        if ($existing !== null) {
            throw ValidationException::withMessages([
                'user_id' => 'Tài khoản này đang có sẵn mã chưa dùng cho tài liệu này: '.$existing->code.' — đưa lại mã đó thay vì cấp thêm mã mới.',
            ]);
        }

        // Bỏ trống thời hạn thì lấy theo thời hạn của chính tài liệu; tài liệu cũng bỏ trống thì
        // null = quyền vĩnh viễn (đúng quy ước sẵn có, xem AccessRight::isCurrentlyActive()).
        $validityMonths = filled($data['validity_months'] ?? null)
            ? (int) $data['validity_months']
            : $product->duration_months;

        return ActivationCode::create([
            'code' => $this->orderActivation->generateUniqueCode(),
            'order_item_id' => null,
            'product_id' => $product->id,
            'assigned_user_id' => $user->id,
            'scope' => $scope->value,
            'status' => ActivationCodeStatus::Unused->value,
            'validity_months' => $validityMonths,
            'note' => $data['note'] ?? null,
            'created_by' => $admin->id,
        ]);
    }

    /**
     * SỬA 18/9 — cấp mã khoá theo tài khoản mà cột chưa tồn tại thì mã cấp ra sẽ KHÔNG khoá
     * được ai cả (ai cũng mở được) — nguy hiểm hơn là báo lỗi. Chặn thẳng, nói rõ việc cần làm.
     */
    private function assertAssignedUserReady(): void
    {
        if (! ActivationCode::supportsAssignedUser()) {
            throw ValidationException::withMessages([
                'user_id' => 'Máy chủ chưa chạy migration cho tính năng này — chạy "php artisan migrate" rồi thử lại.',
            ]);
        }
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
