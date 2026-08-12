<?php

namespace App\Services\Admin;

use App\Enums\Visibility;
use App\Models\Product;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;

/**
 * Gom truy vấn/nhãn cho admin.products.* — "Sản phẩm & Quyền" (5.1).
 */
class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private AccessRightRepositoryInterface $accessRights,
    ) {}

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
