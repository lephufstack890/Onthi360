<?php

namespace App\Services\Access;

use App\Enums\AccessScope;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\AccessRight;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\ActivationCodeRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\AccessGateService;
use App\Services\OrderActivationService;
use App\Support\AccessDecision;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Quyền truy cập / thanh toán (mục 7) — checkout, kích hoạt mã, "Quyền của
 * tôi", trang "Bài khóa". Controller chỉ resolve request/user và render.
 */
class AccessService
{
    /**
     * 3 cửa của 7.3, theo đúng thứ tự AccessGateService kiểm tra: thành
     * viên/lớp trước, rồi quyền cá nhân, rồi tiến độ chung.
     *
     * @var array<string, string>
     */
    private const array GATE_LABELS = [
        'not_in_class_path' => 'Thành viên/lớp',
        'need_personal_access' => 'Quyền học cá nhân',
        'teacher_not_opened' => 'Tiến độ chung',
    ];

    public function __construct(
        private ProductRepositoryInterface $products,
        private MaterialRepositoryInterface $materials,
        private ClassRoomRepositoryInterface $classRooms,
        private AccessRightRepositoryInterface $accessRights,
        private ActivationCodeRepositoryInterface $activationCodes,
        private OrderRepositoryInterface $orders,
        private AccessGateService $accessGate,
        private OrderActivationService $orderActivation,
        private AccessRightStatusService $statusService,
    ) {}

    /** Đồng bộ với printPrice hiển thị ở checkoutData() — TODO chung: giá bản in thật cần cấu hình riêng, chưa có trường trong schema. */
    private const PRINT_PRICE = 50000;

    /** access.checkout (ACC-03) — 7.4/7.5: checkout theo scope, sách mềm bắt buộc. */
    public function checkoutData(User $user, int $productId): array
    {
        $product = $this->products->findOrFail($productId);

        return [
            'product' => $product,
            // Chỉ giáo viên đã được duyệt mới thấy scope "Dùng để dạy" (7.2, 7.5).
            'canTeach' => $user->isTeacherApproved(),
            'printPrice' => self::PRINT_PRICE,
        ];
    }

    /**
     * access.checkout.store (25/8) — SỬA: trước đây nút "Đặt đơn" chưa nối POST thật (ghi chú
     * cũ trong checkout.blade.php), giờ tạo Order + 1 OrderItem thật, trạng thái luôn
     * pending_approval + payment_method=offline (P0 chỉ có thanh toán ngoài hệ thống — admin
     * duyệt, VNPAY còn tắt ở form). ĐÚNG bất biến 7.4 "Tạo đơn ≠ đã thanh toán ≠ đã có quyền":
     * hàm này CHỈ tạo Order/OrderItem, không đụng gì tới AccessRight — mã kích hoạt chỉ sinh ra
     * khi admin duyệt (OrderActivationService::approveOfflineOrder(), đã có sẵn từ trước).
     *
     * @param  array{scope: string, include_print?: bool}  $data
     *
     * @throws ValidationException nếu chọn scope "Dùng để dạy" mà chưa được duyệt giáo viên.
     */
    public function placeOrder(User $user, int $productId, array $data): Order
    {
        $product = $this->products->findOrFail($productId);
        $scope = AccessScope::from($data['scope']);

        if ($scope === AccessScope::TeacherTeaching && ! $user->isTeacherApproved()) {
            throw ValidationException::withMessages([
                'scope' => 'Bạn cần được duyệt giáo viên trước khi đặt quyền "Dùng để dạy".',
            ]);
        }

        $includePrint = (bool) ($data['include_print'] ?? false);
        $totalAmount = $product->price + ($includePrint ? self::PRINT_PRICE : 0);

        return DB::transaction(function () use ($user, $product, $scope, $includePrint, $totalAmount) {
            $order = $this->orders->create([
                'order_no' => $this->generateUniqueOrderNo(),
                'buyer_id' => $user->id,
                'status' => OrderStatus::PendingApproval->value,
                'payment_method' => PaymentMethod::Offline->value,
                'total_amount' => $totalAmount,
            ]);

            // Sách mềm (Product chính) LUÔN có trong đơn (7.4 "sách mềm bắt buộc") — checkbox
            // "mua kèm bản in" chỉ cộng thêm phí giao hàng riêng, KHÔNG tách thành item khác,
            // không đổi quyền số (7.5) — lưu ở include_print của CHÍNH item này là đủ.
            $order->items()->create([
                'product_id' => $product->id,
                'scope' => $scope->value,
                'quantity' => 1,
                'unit_price' => $product->price,
                'include_print' => $includePrint,
                'print_shipping_info' => null,
            ]);

            return $order;
        });
    }

    /** Mã đơn dễ đọc để đối chiếu khi liên hệ hỗ trợ — không phải khoá kỹ thuật (đó là id). */
    private function generateUniqueOrderNo(): string
    {
        do {
            $candidate = 'DH'.now()->format('ymd').strtoupper(Str::random(4));
        } while ($this->orders->query()->where('order_no', $candidate)->exists());

        return $candidate;
    }

    /**
     * access.activate.store (25/8) — SỬA: trước đây form kích hoạt chưa submit thật (ghi chú cũ
     * trong activate.blade.php). Tái dùng NGUYÊN VẸN OrderActivationService::canActivate()/
     * activate() đã có sẵn — không viết lại luật kiểm tra mã ở đây, chỉ tra mã theo code rồi
     * giao hẳn cho service đó (đúng như activationLookup() bên dưới đã làm cho nhánh xem trước).
     *
     * @throws ValidationException nếu không tìm thấy mã hoặc mã không kích hoạt được (lý do lấy
     *                              nguyên văn từ AccessDecision::$message của canActivate()).
     */
    public function activateCode(User $user, string $code): AccessRight
    {
        $code = trim($code);
        $activationCode = $this->activationCodes->query()->where('code', $code)->first();

        if ($activationCode === null) {
            throw ValidationException::withMessages(['code' => 'Không tìm thấy mã kích hoạt này.']);
        }

        $decision = $this->orderActivation->canActivate($activationCode, $user);
        if (! $decision->allowed) {
            throw ValidationException::withMessages(['code' => $decision->message]);
        }

        return $this->orderActivation->activate($activationCode, $user);
    }

    /**
     * access.activate (ACC-02) — nhánh XEM TRƯỚC khi trang được mở qua ?code=... (ví dụ link từ
     * email thông báo mã), hiển thị lý do dùng được/không TRƯỚC khi bấm Kích hoạt. Việc kích
     * hoạt THẬT (submit) đi qua activateCode() ở trên — 2 hàm cố ý tách riêng vì hàm này chỉ
     * XEM, không được phép có tác dụng phụ (không tự activate() khi mới mở trang).
     */
    public function activationLookup(User $user, ?string $code): array
    {
        $code = $code !== null && $code !== '' ? trim($code) : null;

        $activationCode = null;
        $decision = null;

        if ($code !== null) {
            // Chưa có phương thức repository tra theo cột "code" (chỉ CRUD cơ bản +
            // latestWithOrderItemOrder) — dùng query() làm van an toàn (đúng chủ đích
            // của BaseRepositoryInterface::query()).
            $activationCode = $this->activationCodes->query()->where('code', $code)->first();

            $decision = $activationCode
                ? $this->orderActivation->canActivate($activationCode, $user)
                : AccessDecision::deny('code_not_found', 'Không tìm thấy mã kích hoạt này.');
        }

        return [
            'code' => $code,
            'activationCode' => $activationCode,
            'decision' => $decision,
        ];
    }

    /** access.myAccess (ACC-07) — 7.3: Đang có quyền / Sắp hết hạn / Đã hết hạn. */
    public function myAccessData(User $user, string $tab): array
    {
        $all = $this->accessRights->forUserWithProduct($user->id);
        $grouped = $all->groupBy(fn ($right) => $this->statusService->classify($right));

        $tabs = [
            [
                'label' => 'Đang có quyền',
                'href' => route('access.myAccess'),
                'active' => $tab === 'active',
                'count' => $grouped->get(AccessRightStatusService::ACTIVE, collect())->count(),
            ],
            [
                'label' => 'Sắp hết hạn',
                'href' => route('access.myAccess', ['tab' => 'expiring']),
                'active' => $tab === 'expiring',
                'count' => $grouped->get(AccessRightStatusService::EXPIRING, collect())->count(),
            ],
            [
                'label' => 'Đã hết hạn',
                'href' => route('access.myAccess', ['tab' => 'expired']),
                'active' => $tab === 'expired',
                'count' => $grouped->get(AccessRightStatusService::EXPIRED, collect())->count(),
            ],
        ];

        $rights = $grouped->get($tab === 'active' ? AccessRightStatusService::ACTIVE : $tab, collect())->map(fn ($r) => [
            'productId' => $r->product_id,
            'title' => $r->product->title ?? 'Học liệu',
            'expires' => $r->expires_at?->format('d/m/Y') ?? 'Không xác định',
            'status' => match (true) {
                $tab === 'expiring' => 'Sắp hết hạn',
                $tab === 'expired' => 'Đã hết hạn',
                default => 'Còn hiệu lực',
            },
            'tone' => match ($tab) {
                'expiring' => 'warning',
                'expired' => 'neutral',
                default => 'success',
            },
        ])->values()->all();

        return ['tab' => $tab, 'tabs' => $tabs, 'rights' => $rights];
    }

    /**
     * access.blocked (ACC-08) — 7.3: 3 cửa Thành viên/lớp, Quyền cá nhân, Tiến
     * độ chung, tính thật qua AccessGateService::canAccessMaterial() thay cho
     * 3 gate "đã qua" giả trước đây.
     */
    public function blockedGates(User $user, int $materialId, ?int $classRoomId = null): array
    {
        $material = $this->materials->findWithProduct($materialId);

        if (! $material) {
            throw (new ModelNotFoundException())->setModel(Material::class, [$materialId]);
        }

        $classRoom = $classRoomId ? $this->classRooms->find($classRoomId) : null;

        $decision = $this->accessGate->canAccessMaterial($user, $material, $classRoom);

        // Không có ngữ cảnh lớp (truy cập trực tiếp theo sản phẩm, 7.1) thì chỉ
        // cửa "quyền cá nhân" áp dụng — 2 cửa còn lại thuộc về lộ trình lớp.
        $applicableCodes = $classRoom ? array_keys(self::GATE_LABELS) : ['need_personal_access'];

        $gates = [];
        foreach (self::GATE_LABELS as $code => $label) {
            if (! in_array($code, $applicableCodes, true)) {
                continue;
            }

            $failed = in_array($code, $decision->missingGates, true);
            $isPrimary = $failed && $code === $decision->primaryReasonCode;

            $gates[] = [
                'label' => $label,
                'passed' => ! $failed,
                'message' => $isPrimary ? $decision->message : ($failed ? $this->genericFailMessage($code) : $this->genericPassMessage($code)),
                'ctaLabel' => $isPrimary ? $decision->ctaLabel : null,
                'ctaHref' => $isPrimary ? $this->ctaRouteFor($decision->ctaAction) : null,
            ];
        }

        return [
            'gates' => $gates,
            'materialId' => $this->ctaParamFor($decision->ctaAction, $material, $classRoom) ?? $material->id,
        ];
    }

    private function genericPassMessage(string $code): string
    {
        return match ($code) {
            'not_in_class_path' => 'Bạn là thành viên của lớp và học liệu này có trong lộ trình lớp.',
            'need_personal_access' => 'Bạn đã có quyền học cá nhân với học liệu này.',
            'teacher_not_opened' => 'Giáo viên đã mở nội dung này theo tiến độ lớp.',
            default => 'Đã qua.',
        };
    }

    private function genericFailMessage(string $code): string
    {
        return match ($code) {
            'not_in_class_path' => 'Học liệu này không có trong lộ trình lớp của bạn.',
            'need_personal_access' => 'Bạn cần quyền học liệu để mở bài.',
            'teacher_not_opened' => 'Giáo viên chưa mở nội dung này.',
            default => 'Chưa qua.',
        };
    }

    /** ctaAction (chuỗi ngữ nghĩa của AccessDecision) -> route name thật đang tồn tại. */
    private function ctaRouteFor(?string $ctaAction): ?string
    {
        return match ($ctaAction) {
            'purchase_or_activate' => 'access.checkout',
            'view_class_roadmap' => 'student.classes.show',
            'browse_courses' => 'courses.index',
            default => null,
        };
    }

    /** Tham số cho route ở trên — route('...', $materialId) trong Blade chỉ nhận 1 giá trị. */
    private function ctaParamFor(?string $ctaAction, Material $material, ?ClassRoom $classRoom): ?int
    {
        return match ($ctaAction) {
            'purchase_or_activate' => $material->product_id,
            'view_class_roadmap' => $classRoom?->id,
            default => null,
        };
    }
}
