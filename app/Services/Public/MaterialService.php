<?php

namespace App\Services\Public;

use App\Enums\ProductType;
use App\Enums\ReviewTargetType;
use App\Models\Material;
use App\Models\Product;
use App\Models\RatingSummary;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Tài liệu công khai (PUB-05/06, 4.1 "tabs Sách/Chuyên đề/Đề thi" + 7.5 "màn mua theo vai
 * trò"). Product type=course không hiển thị ở đây — đó là khóa học
 * (App\Services\Public\CourseService), giữ đúng 4.3 "Khóa học khác Tài liệu".
 */
class MaterialService
{
    /** query-tab (?tab=) -> ProductType — khớp 3 tab BA 4.1. */
    private const TABS = [
        'sach' => ProductType::Book,
        'chuyen-de' => ProductType::Topic,
        'de-thi' => ProductType::Exam,
    ];

    public function __construct(
        private ProductRepositoryInterface $products,
        private RatingSummaryRepositoryInterface $ratingSummaries,
    ) {}

    /** materials.index — 3 tab theo ProductType, chỉ hiển thị đã phát hành + công khai. */
    public function indexData(string $tab): array
    {
        $type = self::TABS[$tab] ?? self::TABS['sach'];

        $counts = [];
        foreach (self::TABS as $key => $productType) {
            $counts[$key] = (clone $this->baseQuery())->where('type', $productType->value)->count();
        }

        $tabs = [
            ['label' => '📘 Sách', 'href' => route('materials.index'), 'active' => $tab === 'sach', 'count' => $counts['sach']],
            ['label' => '🗂️ Chuyên đề', 'href' => route('materials.index', ['tab' => 'chuyen-de']), 'active' => $tab === 'chuyen-de', 'count' => $counts['chuyen-de']],
            ['label' => '📝 Đề thi', 'href' => route('materials.index', ['tab' => 'de-thi']), 'active' => $tab === 'de-thi', 'count' => $counts['de-thi']],
        ];

        $products = $this->baseQuery()->where('type', $type->value)->latest()->limit(40)->get();

        // 1 câu truy vấn duy nhất cho material gốc đại diện + rating của TẤT CẢ sản phẩm
        // trong trang — tránh N+1 (mỗi sản phẩm 2 câu) khi có nhiều tài liệu.
        $representativeIdByProductId = $this->representativeMaterialIds($products->pluck('id')->all());
        $ratingsByMaterialId = $this->ratingSummariesByMaterialId($representativeIdByProductId->values()->all());

        return [
            'tabs' => $tabs,
            'materials' => $products->map(
                fn (Product $p) => $this->mapCard($p, $representativeIdByProductId->get($p->id), $ratingsByMaterialId)
            )->all(),
        ];
    }

    /** materials.show — 1 tài liệu + mục lục (Material gốc) + lựa chọn quyền theo vai trò (7.5, qua access.checkout). */
    public function showData(int $productId): array
    {
        $product = $this->baseQuery()
            ->with(['materials' => fn ($q) => $q->orderBy('order')])
            ->findOrFail($productId);

        $representativeId = $product->materials->first()?->id;
        $summary = $representativeId !== null
            ? $this->ratingSummaries->findForTarget(ReviewTargetType::Material, $representativeId)
            : null;

        return [
            'material' => $product,
            'toc' => $product->materials->map(fn ($m) => ['id' => $m->id, 'title' => $m->title])->all(),
            'ratingAverage' => $summary?->avg_rating !== null ? (float) $summary->avg_rating : null,
            'ratingCount' => $summary->review_count ?? 0,
        ];
    }

    /**
     * Trang chủ (PUB-01/02, 12.1) — "Tài liệu nổi bật": MỚI PHÁT HÀNH NHẤT gộp chung CẢ 3
     * loại (Sách/Chuyên đề/Đề thi), khác với materials.index vốn luôn lọc riêng theo TỪNG
     * tab. Tái dùng đúng baseQuery()/mapCard() để không lặp lại luật lọc (chỉ
     * published+public, không lộ loại "course") ở một chỗ thứ hai.
     */
    public function featuredData(int $limit = 4): array
    {
        $products = $this->baseQuery()->latest()->limit($limit)->get();

        $representativeIdByProductId = $this->representativeMaterialIds($products->pluck('id')->all());
        $ratingsByMaterialId = $this->ratingSummariesByMaterialId($representativeIdByProductId->values()->all());

        return $products->map(
            fn (Product $p) => $this->mapCard($p, $representativeIdByProductId->get($p->id), $ratingsByMaterialId)
        )->all();
    }

    /** Chỉ đã phát hành + công khai — Product riêng tư (visibility=private) không lộ ra catalog công khai; loại "course" thuộc Khóa học, không thuộc Tài liệu (4.3). */
    private function baseQuery()
    {
        return $this->products->query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->where('type', '!=', ProductType::Course->value);
    }

    private function mapCard(Product $product, ?int $representativeMaterialId, Collection $ratingsByMaterialId): array
    {
        $summary = $representativeMaterialId !== null ? $ratingsByMaterialId->get($representativeMaterialId) : null;

        $priceLabel = $product->price > 0 ? number_format($product->price).'đ' : 'Miễn phí';
        if ($product->has_print_option) {
            $priceLabel .= ' · Có bản in';
        }

        [$badgeLabel, $badgeTone] = $product->price > 0 ? ['Cần kích hoạt', 'warning'] : ['Công khai', 'info'];

        return [
            'id' => $product->id,
            'title' => $product->title,
            'meta' => $priceLabel,
            'average' => $summary?->avg_rating !== null ? (float) $summary->avg_rating : null,
            'count' => $summary->review_count ?? 0,
            'badge' => $badgeLabel,
            'tone' => $badgeTone,
        ];
    }

    /**
     * Rating "tài liệu" (9.1) được gắn vào 1 Material CỤ THỂ, không phải Product — xem
     * App\Services\Review\ReviewService::findTarget() (target_type=material tra theo
     * MaterialRepositoryInterface). Dùng material gốc đầu tiên (order nhỏ nhất, không có
     * parent) của Product làm đại diện — đúng cách App\Services\ReviewEligibilityService
     * ::eligibleForMaterialReview() suy ngược Product từ 1 Material ($material->product).
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, int> keyed theo product_id, giá trị là material_id đại diện.
     */
    private function representativeMaterialIds(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        // orderBy('product_id') TRƯỚC orderBy('order') để ->unique('product_id') giữ đúng
        // material có 'order' NHỎ NHẤT của TỪNG sản phẩm (không phải nhỏ nhất toàn cục).
        return Material::query()
            ->whereIn('product_id', $productIds)
            ->whereNull('parent_id')
            ->orderBy('product_id')
            ->orderBy('order')
            ->get(['id', 'product_id'])
            ->unique('product_id')
            ->pluck('id', 'product_id');
    }

    /** @param  array<int, int>  $materialIds
     * @return Collection<int, RatingSummary> keyed theo material id. */
    private function ratingSummariesByMaterialId(array $materialIds): Collection
    {
        if ($materialIds === []) {
            return collect();
        }

        return RatingSummary::query()
            ->where('target_type', ReviewTargetType::Material)
            ->whereIn('target_id', $materialIds)
            ->get()
            ->keyBy('target_id');
    }
}
