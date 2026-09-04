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

class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private AccessRightRepositoryInterface $accessRights,
        private MaterialRepositoryInterface $materials,
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
            'grades' => ['Lớp 6', 'Lớp 7', 'Lớp 8', 'Lớp 9', 'Lớp 10', 'Lớp 11', 'Lớp 12'],
        ];
    }

    /** admin.products.create — dữ liệu tĩnh cho form. */
    public function createFormData(): array
    {
        return $this->formOptions();
    }

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

        if (array_key_exists('cover_image_path', $data)) {
            $attributes['cover_image_path'] = $data['cover_image_path'];
        }

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
            ['label' => 'Tài liệu', 'href' => route('admin.products.index'), 'active' => true, 'count' => $this->products->count()],
            ['label' => 'Quyền đã cấp', 'href' => route('admin.access-rights.index'), 'active' => false, 'count' => $this->accessRights->count()],
        ];

        $products = $this->products->latest(50)->map(fn ($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'type' => $p->type->value,
            'price' => number_format($p->price).'đ',
            'visibility' => $p->visibility === Visibility::Public ? 'Công khai' : 'Riêng tư',
            'tone' => $p->visibility === Visibility::Public ? 'info' : 'neutral',
        ])->all();

        return ['tabs' => $tabs, 'products' => $products];
    }

    /**
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

        $allMaterials = $this->materials->query()
            ->where('product_id', $productId)
            ->orderBy('order')
            ->get(['id', 'parent_id', 'title', 'pdf_path', 'status']);

        return [
            'product' => $product,
            'accessRightRows' => $accessRightRows,
            'accessRightCount' => $this->accessRights->query()->where('product_id', $productId)->count(),
            'materialsTree' => $this->buildMaterialsTree($allMaterials, null),
            'exercises' => $this->contentService->productExercisesFor($product),
            'chapters' => $this->contentService->productChaptersFor($product),
            'materialsList' => $this->contentService->productMaterialsFor($product),
        ];
    }

    /**
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

        if ($r->expires_at->isPast()) {
            return ['Hết hạn', 'danger'];
        }

        if ($r->expires_at->diffInDays(now(), false) >= -self::EXPIRING_SOON_WINDOW_DAYS) {
            return ['Sắp hết hạn', 'warning'];
        }

        return ['Hiệu lực', 'success'];
    }
}
