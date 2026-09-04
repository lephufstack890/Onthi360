<?php

namespace App\Services\Admin;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Enums\ContentStatus;
use App\Enums\ProductType;
use App\Enums\Visibility;
use App\Models\AccessRight;
use App\Models\Material;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Gom truy vấn/nhãn cho admin.products.* — "Sản phẩm & Quyền" (5.1). Trang chi tiết sản
 * phẩm PHẢI làm rõ (note họp 13/8, mục 2): sản phẩm dùng để làm gì + tạo từ khi nào (đã có
 * sẵn ở phần "Thông tin sản phẩm"), danh sách các quyền đã cấp cho sản phẩm này, và với
 * quyền cấp từ đơn hàng thì xem được đã thanh toán/duyệt lúc nào (7.4).
 */
class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private AccessRightRepositoryInterface $accessRights,
        private MaterialRepositoryInterface $materials,
        // SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — danh sách/quét dọn bài tập của sản phẩm
        // dùng lại nguyên bộ logic Question đã có ở ContentService (productExercise*()), tránh
        // lặp lại (đặc biệt là dọn file đính kèm trên đĩa khi xoá) ở đây.
        private ContentService $contentService,
    ) {}

    /** @return array{types: array, visibilities: array, statuses: array} */
    private function formOptions(): array
    {
        return [
            'types' => [
                ProductType::Book->value => 'Sách', ProductType::Topic->value => 'Chuyên đề',
                ProductType::Exam->value => 'Bộ đề', ProductType::Course->value => 'Khóa học',
            ],
            'visibilities' => [Visibility::Public->value => 'Công khai', Visibility::Private->value => 'Riêng tư'],
            'statuses' => [
                ContentStatus::Draft->value => 'Bản nháp', ContentStatus::Published->value => 'Xuất bản',
                ContentStatus::Archived->value => 'Lưu trữ',
            ],
            // Danh sách khối lớp — đồng bộ với App\Services\Admin\CourseService (Khóa & Lớp)
            // để "Khối lớp" chọn từ select thay vì gõ tay, tránh lệch dữ liệu giữa 2 module.
            'grades' => ['Lớp 6', 'Lớp 7', 'Lớp 8', 'Lớp 9', 'Lớp 10', 'Lớp 11', 'Lớp 12'],
        ];
    }

    /** admin.products.create — dữ liệu tĩnh cho form. */
    public function createFormData(): array
    {
        return $this->formOptions();
    }

    /**
     * admin.products.store — slug tự sinh từ tiêu đề (giống Course), tự thêm số thứ tự nếu
     * trùng. owner_type luôn "shared" — sản phẩm tạo ở đây thuộc Admin/Editor quản lý trực
     * tiếp (5.1), khác với sản phẩm gắn riêng cho 1 giáo viên (owner_type=teacher, nếu có,
     * không có UI tạo ở admin này).
     */
    public function store(array $data): Product
    {
        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->products->query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $this->products->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'cover_image_path' => $data['cover_image_path'] ?? null,
            'subject' => $data['subject'] ?? null,
            'grade' => $data['grade'] ?? null,
            'topic' => $data['topic'] ?? null,
            'price' => $data['price'] ?? 0,
            'price_teaching' => $data['price_teaching'] ?? 0,
            'has_print_option' => (bool) ($data['has_print_option'] ?? false),
            'duration_months' => $data['duration_months'] ?: null,
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'owner_type' => 'shared',
            'owner_id' => null,
            'created_by' => null,
            // SỬA 27/8 ("4 file đính kèm sản phẩm") — ProductController::applyResourceUploads()
            // chỉ đưa 2 key path/original_name vào $data khi THỰC SỰ có file mới, nên ?? null
            // ở đây là đúng cho lần tạo mới (chưa có gì để giữ nguyên).
            'content_pdf_path' => $data['content_pdf_path'] ?? null,
            'content_pdf_original_name' => $data['content_pdf_original_name'] ?? null,
            'guide_pdf_path' => $data['guide_pdf_path'] ?? null,
            'guide_pdf_original_name' => $data['guide_pdf_original_name'] ?? null,
            'exercise_zip_path' => $data['exercise_zip_path'] ?? null,
            'exercise_zip_original_name' => $data['exercise_zip_original_name'] ?? null,
            'media_path' => $data['media_path'] ?? null,
            'media_original_name' => $data['media_original_name'] ?? null,
        ]);
    }

    /** admin.products.edit — sản phẩm hiện tại + option form. Slug KHÔNG cho sửa (giữ SEO/link). */
    public function editFormData(int $productId): array
    {
        return array_merge($this->formOptions(), [
            'product' => $this->products->findOrFail($productId),
        ]);
    }

    public function update(Product $product, array $data): Product
    {
        $attributes = [
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'subject' => $data['subject'] ?? null,
            'grade' => $data['grade'] ?? null,
            'topic' => $data['topic'] ?? null,
            'price' => $data['price'] ?? 0,
            'price_teaching' => $data['price_teaching'] ?? 0,
            'has_print_option' => (bool) ($data['has_print_option'] ?? false),
            'duration_months' => $data['duration_months'] ?: null,
            'status' => $data['status'],
            'visibility' => $data['visibility'],
        ];

        // Chỉ ghi đè cover_image_path khi controller thực sự có ảnh mới upload (xem
        // ProductController::update) — không có key này trong $data nghĩa là admin không
        // chọn ảnh mới, PHẢI giữ nguyên ảnh cũ, không được ghi đè thành null.
        if (array_key_exists('cover_image_path', $data)) {
            $attributes['cover_image_path'] = $data['cover_image_path'];
        }

        // SỬA 27/8 ("4 file đính kèm sản phẩm") — CÙNG quy tắc trên, áp dụng cho cả 4 cặp
        // path/original_name: chỉ ghi đè khi ProductController::applyResourceUploads() thực sự
        // có file mới (key có mặt trong $data), giữ nguyên file cũ nếu không upload.
        foreach (['content_pdf_path', 'content_pdf_original_name', 'guide_pdf_path', 'guide_pdf_original_name', 'exercise_zip_path', 'exercise_zip_original_name', 'media_path', 'media_original_name'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        return $this->products->update($product, $attributes);
    }

    /** admin.products.destroy — xóa mềm, PHẢI có lý do + audit log (10.4). */
    public function destroy(Product $product, string $reason): void
    {
        Product::$auditReason = $reason;
        $this->products->delete($product);
        Product::$auditReason = null;
    }

    /** @return array{tabs: array, products: array} */
    public function indexData(): array
    {
        $tabs = [
            // SỬA 4/9 (khách yêu cầu đổi tên "Sản phẩm & Quyền" -> "Tài liệu"): nhãn tab này
            // TRƯỚC ĐÂY còn sót "Sản phẩm" (bỏ sót lúc đổi tên vì nằm ở service, không phải
            // trong file blade) — khách phát hiện khi bấm vào trang "Tài liệu" thấy tab không
            // khớp tên mới, đã sửa lại đúng "Tài liệu".
            ['label' => 'Tài liệu', 'href' => route('admin.products.index'), 'active' => true, 'count' => $this->products->count()],
            ['label' => 'Quyền đã cấp', 'href' => route('admin.access-rights.index'), 'active' => false, 'count' => $this->accessRights->count()],
        ];

        // SỬA 4/9 (khách yêu cầu: "chỗ danh sách có field Loại hiển thị tiếng Việt thay vì
        // book,... người dùng khó hiểu") — dùng LẠI đúng nhãn ở $typeLabels (nguồn duy nhất,
        // cũng dùng cho dropdown "Loại tài liệu" ở form Tạo/Sửa, xem formOptions()) thay vì
        // hiện thẳng $p->type->value (chuỗi thô "book"/"topic"/"exam"/"course").
        $typeLabels = $this->formOptions()['types'];

        $products = $this->products->latest(50)->map(fn ($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'type' => $typeLabels[$p->type->value] ?? $p->type->value,
            'price' => number_format($p->price).'đ',
            'visibility' => $p->visibility === Visibility::Public ? 'Công khai' : 'Riêng tư',
            'tone' => $p->visibility === Visibility::Public ? 'info' : 'neutral',
        ])->all();

        return ['tabs' => $tabs, 'products' => $products];
    }

    /**
     * admin.products.show — làm rõ sản phẩm để làm gì/tạo từ khi nào (đã có ở phần thông
     * tin) VÀ danh sách quyền đã cấp cho sản phẩm này, kèm — với quyền đến từ đơn hàng —
     * thời điểm đơn được duyệt/thanh toán (note họp 13/8, mục 2).
     *
     * @return array{product: Product, accessRightRows: array, accessRightCount: int, materialsTree: array}
     */
    public function showData(int $productId): array
    {
        $product = $this->products->findOrFail($productId);

        $recentRights = $this->accessRights->query()
            ->where('product_id', $productId)
            ->with('user')
            ->latest()
            ->limit(50)
            ->get();

        // AccessRight cấp từ đơn hàng lưu source_id = order_item_id (xem
        // App\Services\OrderActivationService::activate()) — tra ngược để lấy order_no +
        // approved_at (đúng thời điểm đơn được duyệt/thanh toán, khác thời điểm kích hoạt).
        $orderItemIds = $recentRights->where('source', 'order')->pluck('source_id')->filter()->unique()->all();
        $orderItemsById = OrderItem::whereIn('id', $orderItemIds)->with('order')->get()->keyBy('id');

        $sourceLabels = [
            'order' => 'Mua hàng', 'gift' => 'Tặng', 'admin_grant' => 'Admin cấp', 'package' => 'Gói',
        ];

        $accessRightRows = $recentRights->map(function (AccessRight $r) use ($orderItemsById, $sourceLabels) {
            [$statusLabel, $tone] = $this->expiryStatus($r);
            $order = $r->source === 'order' ? ($orderItemsById->get($r->source_id)?->order) : null;

            return [
                'id' => $r->id,
                'userName' => $r->user->name ?? '—',
                'scopeLabel' => $r->scope === AccessScope::TeacherTeaching ? 'Dùng để dạy' : 'Học cá nhân',
                'statusLabel' => $statusLabel,
                'tone' => $tone,
                'startsAt' => $r->starts_at,
                'expiresAt' => $r->expires_at,
                'sourceLabel' => $sourceLabels[$r->source] ?? $r->source,
                'orderNo' => $order?->order_no,
                'paidAt' => $order?->approved_at,
            ];
        })->values()->all();

        // SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền"): trước đây trang này chỉ hiện học
        // liệu CẤP 1 (qua $product->materials, quan hệ lọc whereNull('parent_id')) — giờ lấy
        // TẤT CẢ học liệu của sản phẩm (mọi cấp) rồi dựng cây theo parent_id, y hệt cách
        // App\Services\Public\MaterialService::buildTocTree() làm cho trang công khai, để admin
        // quản lý (thêm/sửa/xoá) đúng cấu trúc chương/bài thật đang có, không chỉ thấy 1 lớp.
        $allMaterials = $this->materials->query()
            ->where('product_id', $productId)
            ->orderBy('order')
            ->get(['id', 'parent_id', 'title', 'pdf_path', 'status']);

        return [
            'product' => $product,
            'accessRightRows' => $accessRightRows,
            'accessRightCount' => $this->accessRights->query()->where('product_id', $productId)->count(),
            'materialsTree' => $this->buildMaterialsTree($allMaterials, null),
            // SỬA 31/8 — danh sách "Bài tập đính kèm" (nhập từ ZIP); tự dọn bản nháp bỏ dở
            // ngay trong productExercisesFor(), xem ContentService::discardAbandonedDraftsFor().
            'exercises' => $this->contentService->productExercisesFor($product),
            // SỬA 4/9 (khách yêu cầu "Chương/Phần/Đề") — mục lục chương/phần/đề (tái dùng
            // Material type=chapter) + danh sách học liệu thật (PDF/audio/ảnh) đã gắn theo
            // từng mục, xem ContentService::productChaptersFor()/productMaterialsFor().
            'chapters' => $this->contentService->productChaptersFor($product),
            'materialsList' => $this->contentService->productMaterialsFor($product),
        ];
    }

    /**
     * Dựng cây học liệu đa cấp cho trang chi tiết sản phẩm (admin) từ danh sách Material
     * PHẲNG (đã orderBy('order')) — xem ghi chú ở showData() phía trên. Cùng cách làm với
     * App\Services\Public\MaterialService::buildTocTree() (trang công khai) nhưng thêm
     * 'statusValue' vì admin cần thấy trạng thái phát hành của từng bài, khác trang công khai
     * chỉ cần biết đọc được hay không.
     *
     * @param  Collection<int, Material>  $materials
     * @return array<int, array{id:int,title:string,hasContent:bool,statusValue:string,children:array}>
     */
    private function buildMaterialsTree(Collection $materials, ?int $parentId): array
    {
        return $materials
            ->where('parent_id', $parentId)
            ->map(fn (Material $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'hasContent' => $m->pdf_path !== null,
                'statusValue' => $m->status->value,
                'children' => $this->buildMaterialsTree($materials, $m->id),
            ])
            ->values()
            ->all();
    }

    /**
     * Phân loại cửa sổ hết hạn cho 1 AccessRight — trả về [nhãn, tone].
     * SỬA 4/9 (khách phát hiện lần 1: "chỗ Quyền đã cấp cho tài liệu này hiển thị trạng thái
     * sai — còn 5 ngày thì mới là đang sắp hết hạn"; phát hiện lần 2, sau khi đổi ngưỡng
     * 14->5 vẫn còn sai: "27/08/2026 — 27/08/2027, năm 2027 sao lại sắp hết hạn"): bản đầu
     * dùng $r->expires_at->diffInDays(now()) — mặc định $absolute=true của Carbon LUÔN trả số
     * dương bất kể ngày hết hạn ở tương lai xa cỡ nào, nên so sánh "<= N" có thể sai lệch tuỳ
     * bản Carbon đang cài. Viết lại theo ĐÚNG công thức đã dùng (và chạy đúng) ở
     * App\Services\Access\AccessRightStatusService::classify() (trang "Quyền của tôi" phía
     * học viên): tính trực tiếp từ 2 mốc ngày (isPast()/isFuture()) thay vì diffInDays dấu —
     * "còn 5 ngày cuối mới là sắp hết hạn, còn vượt quá là hết hạn" (khách chốt). CỐ Ý ngưỡng
     * 5 ngày ở màn ADMIN NÀY khác ngưỡng 14 ngày ở AccessRightStatusService — khách chỉ yêu
     * cầu sửa màn admin này.
     */
    private const EXPIRING_SOON_WINDOW_DAYS = 5;

    private function expiryStatus(AccessRight $r): array
    {
        if ($r->status !== AccessRightStatus::Active) {
            return match ($r->status) {
                AccessRightStatus::Expired => ['Hết hạn', 'danger'],
                AccessRightStatus::Revoked => ['Đã thu hồi', 'neutral'],
                default => [(string) $r->status->value, 'neutral'],
            };
        }

        if ($r->expires_at === null) {
            return ['Hiệu lực', 'success'];
        }

        // "Còn vượt quá là hết hạn" — dù cột status trong DB còn ghi Active (job tự động đổi
        // status có thể chưa chạy tới), ngày đã qua thì vẫn phải hiện đúng "Hết hạn".
        if ($r->expires_at->isPast()) {
            return ['Hết hạn', 'danger'];
        }

        if ($r->expires_at->diffInDays(now(), false) >= -self::EXPIRING_SOON_WINDOW_DAYS) {
            return ['Sắp hết hạn', 'warning'];
        }

        return ['Hiệu lực', 'success'];
    }
}
