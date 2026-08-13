<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Enums\ProductType;
use App\Enums\Visibility;
use App\Models\Product;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Gom truy vấn/nhãn cho admin.products.* — "Sản phẩm & Quyền" (5.1).
 */
class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private AccessRightRepositoryInterface $accessRights,
    ) {}

    /** @return array{types: array, visibilities: array, statuses: array} */
    private function formOptions(): array
    {
        return [
            'types' => [
                ProductType::Book->value => 'Sách', ProductType::Topic->value => 'Chuyên đề',
                ProductType::Exam->value => 'Đề thi', ProductType::Course->value => 'Khóa học',
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
            'has_print_option' => (bool) ($data['has_print_option'] ?? false),
            'duration_months' => $data['duration_months'] ?: null,
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'owner_type' => 'shared',
            'owner_id' => null,
            'created_by' => null,
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
            ['label' => 'Sản phẩm', 'href' => route('admin.products.index'), 'active' => true, 'count' => $this->products->count()],
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

    /** @return array{product: Product} */
    public function showData(int $productId): array
    {
        return ['product' => $this->products->findOrFail($productId)];
    }
}
