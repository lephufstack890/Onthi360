<?php

namespace App\Services\Access;

use App\Enums\AccessScope;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\AccessRight;
use App\Models\ClassRoom;
use App\Models\Material;
use App\Models\Order;
use App\Models\Question;
use App\Models\Role;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            // SỬA 25/8 (2): hiện số dư token ngay ở trang đặt đơn để học sinh biết đủ trả ngay
            // hay chưa TRƯỚC khi bấm "Đặt đơn" — tránh phải bấm thử rồi mới biết thiếu.
            'tokenBalance' => $user->token_balance,
        ];
    }

    /**
     * access.resource (27/8, "4 file đính kèm sản phẩm") — tải/xem 1 trong 4 tài nguyên gắn
     * thẳng vào Product: PDF nội dung chính (content — thay khối "Học liệu"/Material cây
     * chương/mục đã bỏ, xem SỬA 27/8 (2)), PDF hướng dẫn (guide), ZIP bài tập (exercise), học
     * liệu media (media). Quyền tải/xem dùng lại AccessGateService::canAccessProduct() — kiểm
     * tra Ở ĐÂY, không tin route trước đã kiểm tra (cùng nguyên tắc "2 request độc lập" như
     * MaterialReadService::pdfFile()).
     */
    public function downloadResource(User $user, int $productId, string $kind): StreamedResponse
    {
        $product = $this->products->findOrFail($productId);

        abort_unless($this->accessGate->canAccessProduct($user, $product)->allowed, 403);

        // SỬA 28/8 ("học sinh dc xem toàn bộ file chỉ trừ file hướng dẫn là không được xem"):
        // PDF hướng dẫn dành riêng cho giáo viên (giáo án/hướng dẫn giảng dạy) — vai trò học
        // sinh (KHÔNG kiêm giáo viên/admin/super_admin) không được xem/tải file này dù đã có
        // quyền sản phẩm còn hiệu lực. Chặn thật ở đây (không chỉ ẩn link ở UI) vì
        // student.library.index chỉ là 1 cách vào, request trực tiếp route này vẫn phải bị
        // chặn đúng luật.
        if ($kind === 'guide'
            && $user->hasAnyRole(Role::STUDENT)
            && ! $user->hasAnyRole(Role::TEACHER, Role::ADMIN, Role::SUPER_ADMIN)) {
            abort(403);
        }

        [$path, $originalName] = match ($kind) {
            'content' => [$product->content_pdf_path, $product->content_pdf_original_name],
            'guide' => [$product->guide_pdf_path, $product->guide_pdf_original_name],
            'exercise' => [$product->exercise_zip_path, $product->exercise_zip_original_name],
            'media' => [$product->media_path, $product->media_original_name],
            default => [null, null],
        };

        abort_if(blank($path), 404);

        // ZIP bài tập luôn TẢI VỀ (không đọc trực tiếp được trên web) — PDF (nội dung
        // chính/hướng dẫn)/học liệu media thì mở/nghe/xem trực tiếp trên trình duyệt
        // (response() thay vì download()).
        return $kind === 'exercise'
            ? Storage::disk('local')->download($path, $originalName ?: basename($path))
            : Storage::disk('local')->response($path, $originalName ?: basename($path));
    }

    /**
     * access.resource.exerciseAttachment (31/8, "ZIP bài tập" gắn vào sản phẩm) — đề bài/lời
     * giải/code mẫu của 1 BÀI TẬP cụ thể (Question, product_id khác null), khác
     * downloadResource() ở trên vốn phục vụ 4 tệp gắn THẲNG vào Product. Cùng quy tắc kiểm tra
     * quyền: canAccessProduct() Ở ĐÂY (không tin route/UI đã kiểm tra trước).
     *
     * 'solution' (lời giải) và 'reference' (code mẫu) CHỈ giáo viên/admin xem được — học sinh
     * đang tự làm bài không được xem trước đáp án (cùng tinh thần chặn kind='guide' ở
     * downloadResource() phía trên). 'statement' (đề bài) thì ai có quyền sản phẩm cũng xem
     * được — không có đề thì không thể làm bài.
     */
    public function downloadExerciseAttachment(User $user, int $productId, int $questionId, string $kind): StreamedResponse
    {
        $product = $this->products->findOrFail($productId);

        abort_unless($this->accessGate->canAccessProduct($user, $product)->allowed, 403);

        /** @var Question $exercise */
        $exercise = Question::where('product_id', $productId)->findOrFail($questionId);

        if (in_array($kind, ['solution', 'reference'], true)
            && $user->hasAnyRole(Role::STUDENT)
            && ! $user->hasAnyRole(Role::TEACHER, Role::ADMIN, Role::SUPER_ADMIN)) {
            abort(403);
        }

        $attachment = $exercise->metadata['attachments'][$kind] ?? null;
        abort_if(! isset($attachment['path']), 404);

        return Storage::disk('local')->download($attachment['path'], $attachment['filename'] ?? basename($attachment['path']));
    }

    /**
     * access.checkout.store (25/8, SỬA 25/8 (2)) — nút "Đặt đơn" tạo Order + 1 OrderItem thật.
     * 2 phương thức thanh toán:
     * - Offline (P0 gốc): trạng thái luôn pending_approval, KHÔNG đụng gì tới AccessRight — mã
     *   kích hoạt chỉ sinh ra khi admin duyệt (OrderActivationService::approveOfflineOrder()).
     * - Token (SỬA 25/8 (2), "phải trừ token"): trừ token_balance NGAY, đơn hoàn tất tức thì,
     *   cấp quyền ngay qua OrderActivationService::completeInstantly() — không chờ admin.
     * Cả 2 nhánh đều giữ đúng bất biến 7.4 "Tạo đơn ≠ đã thanh toán ≠ đã có quyền": AccessRight
     * luôn chỉ được tạo qua OrderActivationService::activate(), không bao giờ tạo trực tiếp ở đây.
     *
     * SỬA 25/8 (2), "cái nào đã đặt rồi thì không được đặt nữa": chặn đặt trùng nếu user ĐÃ có
     * quyền còn hiệu lực (đúng scope) hoặc đang có đơn chưa xử lý xong cho đúng sản phẩm+scope
     * này — quyền cũ đã HẾT HẠN thì vẫn cho đặt lại (luồng "Gia hạn" ở access.myAccess).
     *
     * @param  array{scope: string, include_print?: bool, payment_method?: string}  $data
     *
     * @throws ValidationException nếu chọn scope "Dùng để dạy" mà chưa được duyệt giáo viên,
     *                              nếu đã có quyền/đơn đang xử lý cho sản phẩm này, hoặc (khi
     *                              payment_method=token) nếu số dư token không đủ — lỗi này
     *                              dùng riêng key 'insufficient_token' để Controller biết mà
     *                              chuyển hướng sang trang nạp token (wallet.index) thay vì
     *                              quay lại trang đặt đơn.
     */
    public function placeOrder(User $user, int $productId, array $data): Order
    {
        $product = $this->products->findOrFail($productId);
        $scope = AccessScope::from($data['scope']);
        $paymentMethod = PaymentMethod::from($data['payment_method'] ?? PaymentMethod::Offline->value);

        if ($scope === AccessScope::TeacherTeaching && ! $user->isTeacherApproved()) {
            throw ValidationException::withMessages([
                'scope' => 'Bạn cần được duyệt giáo viên trước khi đặt quyền "Dùng để dạy".',
            ]);
        }

        $alreadyActive = $this->accessRights->forUserWithProduct($user->id)
            ->contains(fn (AccessRight $ar) => $ar->product_id === $product->id
                && $ar->scope === $scope
                && $ar->isCurrentlyActive());

        if ($alreadyActive) {
            throw ValidationException::withMessages([
                'product' => 'Bạn đã có quyền còn hiệu lực với học liệu này rồi — không cần đặt lại.',
            ]);
        }

        if ($this->orders->hasOpenOrderForProduct($user->id, $product->id, $scope->value)) {
            throw ValidationException::withMessages([
                'product' => 'Bạn đã có một đơn đang xử lý cho học liệu này — vui lòng chờ xử lý xong trước khi đặt thêm.',
            ]);
        }

        // SỬA 26/8 ("giá để học"/"giá để dạy" tách riêng): giá tính vào đơn PHẢI theo đúng
        // scope người mua chọn — Product::$price_teaching mới thêm cho scope TeacherTeaching,
        // scope PersonalLearning vẫn dùng Product::$price như cũ.
        $unitPrice = $scope === AccessScope::TeacherTeaching ? $product->price_teaching : $product->price;

        $includePrint = (bool) ($data['include_print'] ?? false);
        $totalAmount = $unitPrice + ($includePrint ? self::PRINT_PRICE : 0);

        if ($paymentMethod === PaymentMethod::Token && $user->token_balance < $totalAmount) {
            throw ValidationException::withMessages([
                'insufficient_token' => 'Số dư token không đủ để thanh toán — vui lòng nạp thêm token.',
            ]);
        }

        return DB::transaction(function () use ($user, $product, $scope, $includePrint, $totalAmount, $unitPrice, $paymentMethod) {
            $order = $this->orders->create([
                'order_no' => $this->generateUniqueOrderNo(),
                'buyer_id' => $user->id,
                'status' => ($paymentMethod === PaymentMethod::Token ? OrderStatus::Completed : OrderStatus::PendingApproval)->value,
                'payment_method' => $paymentMethod->value,
                'total_amount' => $totalAmount,
            ]);

            // Sách mềm (Product chính) LUÔN có trong đơn (7.4 "sách mềm bắt buộc") — checkbox
            // "mua kèm bản in" chỉ cộng thêm phí giao hàng riêng, KHÔNG tách thành item khác,
            // không đổi quyền số (7.5) — lưu ở include_print của CHÍNH item này là đủ.
            $order->items()->create([
                'product_id' => $product->id,
                'scope' => $scope->value,
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'include_print' => $includePrint,
                'print_shipping_info' => null,
            ]);

            if ($paymentMethod === PaymentMethod::Token) {
                // Trừ token TRONG transaction — nếu completeInstantly() phía sau lỗi thì rollback
                // luôn cả 2, không để mất token mà không ra quyền (16 mục 3).
                $user->decrement('token_balance', $totalAmount);

                $this->orderActivation->completeInstantly($order, $user);
            }

            return $order->fresh();
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
     * access.history (mới 25/8 (2)) — "nhớ lưu lại lịch sử đặt mua có học sinh luôn": liệt kê
     * TOÀN BỘ Order do CHÍNH user này đặt (buyer_id), mới nhất trước — khác access.myAccess vốn
     * chỉ hiện AccessRight (quyền hiện có), không hiện Order (đơn) thô kèm trạng thái xử lý.
     */
    public function purchaseHistoryData(User $user): array
    {
        $orders = $this->orders->forBuyerWithItems($user->id);

        return [
            'orders' => $orders->map(fn (Order $o) => [
                'orderNo' => $o->order_no,
                'createdAt' => $o->created_at,
                'items' => $o->items->map(fn ($item) => [
                    'title' => $item->product->title ?? 'Học liệu',
                    'scope' => $item->scope === AccessScope::TeacherTeaching ? 'Dùng để dạy' : 'Học cá nhân',
                ])->all(),
                'totalAmount' => $o->total_amount,
                'paymentMethod' => match ($o->payment_method) {
                    PaymentMethod::Token => 'Token',
                    PaymentMethod::Vnpay => 'VNPAY',
                    default => 'Ngoài hệ thống',
                },
                'status' => match ($o->status) {
                    OrderStatus::Completed => 'Hoàn tất',
                    OrderStatus::Approved => 'Đã duyệt',
                    OrderStatus::PendingApproval => 'Chờ duyệt',
                    OrderStatus::PendingPayment => 'Chờ thanh toán',
                    OrderStatus::Rejected => 'Từ chối',
                    OrderStatus::Canceled => 'Đã huỷ',
                    OrderStatus::Refunded => 'Đã hoàn tiền',
                    default => 'Khác',
                },
                'tone' => match ($o->status) {
                    OrderStatus::Completed, OrderStatus::Approved => 'success',
                    OrderStatus::PendingApproval, OrderStatus::PendingPayment => 'warning',
                    default => 'neutral',
                },
            ])->all(),
        ];
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
