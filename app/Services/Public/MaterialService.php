<?php

namespace App\Services\Public;

use App\Enums\AccessScope;
use App\Enums\ProductType;
use App\Enums\ReviewTargetType;
use App\Models\AccessRight;
use App\Models\Material;
use App\Models\Product;
use App\Models\RatingSummary;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Tài liệu công khai (PUB-05/06, 4.1 "tabs Sách/Chuyên đề/Đề thi" + 7.5 "màn mua theo vai
 * trò"). Product type=course không hiển thị ở đây — đó là khóa học
 * (App\Services\Public\CourseService), giữ đúng 4.3 "Khóa học khác Tài liệu".
 *
 * "Tic xanh" (4.1) — tài liệu mà NGƯỜI ĐANG XEM (nếu đã đăng nhập) đã sở hữu quyền học cá
 * nhân (AccessRight.scope=personal_learning, còn hiệu lực) thì đánh dấu 'owned' => true cho
 * thẻ/trang chi tiết tương ứng. Khách (chưa đăng nhập, $viewer=null) luôn thấy 'owned' =>
 * false cho mọi tài liệu — không có false-positive.
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
        private AccessRightRepositoryInterface $accessRights,
    ) {}

    /** materials.index — 3 tab theo ProductType, chỉ hiển thị đã phát hành + công khai. */
    public function indexData(string $tab, ?User $viewer = null): array
    {
        $type = self::TABS[$tab] ?? self::TABS['sach'];

        $counts = [];
        foreach (self::TABS as $key => $productType) {
            $counts[$key] = (clone $this->baseQuery())->where('type', $productType->value)->count();
        }

        $tabs = [
            ['label' => '📘 Sách', 'href' => route('materials.index'), 'active' => $tab === 'sach', 'count' => $counts['sach']],
            ['label' => '🗂️ Chuyên đề', 'href' => route('materials.index', ['tab' => 'chuyen-de']), 'active' => $tab === 'chuyen-de', 'count' => $counts['chuyen-de']],
            ['label' => '📝 Bộ đề', 'href' => route('materials.index', ['tab' => 'de-thi']), 'active' => $tab === 'de-thi', 'count' => $counts['de-thi']],
        ];

        $products = $this->baseQuery()->where('type', $type->value)->latest()->limit(40)->get();

        // 1 câu truy vấn duy nhất cho material gốc đại diện + rating của TẤT CẢ sản phẩm
        // trong trang — tránh N+1 (mỗi sản phẩm 2 câu) khi có nhiều tài liệu.
        $representativeIdByProductId = $this->representativeMaterialIds($products->pluck('id')->all());
        $ratingsByMaterialId = $this->ratingSummariesByMaterialId($representativeIdByProductId->values()->all());
        $ownedProductIds = $this->ownedProductIds($viewer, $products->pluck('id')->all());

        return [
            'tabs' => $tabs,
            'materials' => $products->map(
                fn (Product $p) => $this->mapCard($p, $representativeIdByProductId->get($p->id), $ratingsByMaterialId, $ownedProductIds)
            )->all(),
        ];
    }

    /** materials.show — 1 tài liệu + mục lục ĐA CẤP (mọi Material của Product) + lựa chọn quyền theo vai trò (7.5, qua access.checkout). */
    public function showData(int $productId, ?User $viewer = null): array
    {
        $product = $this->baseQuery()->findOrFail($productId);

        // SỬA 25/8 (8 — "mục lục đa cấp"): trước đây dùng quan hệ Product::materials(), quan
        // hệ này LỌC whereNull('parent_id') nên mục lục chỉ có Material CẤP 1 — bài con lồng
        // bên trong 1 chương (VD "Chương 1" chứa nhiều "BÀI") không bao giờ xuất hiện, dù
        // logic đọc/mua vẫn đúng. Giờ lấy TẤT CẢ Material của sản phẩm (mọi cấp) trong ĐÚNG 1
        // câu truy vấn phẳng rồi dựng CÂY theo parent_id ở PHP (buildTocTree()) — Blade dùng
        // đệ quy (partials.materials-toc-item) để hiển thị bao nhiêu cấp cũng được.
        $allMaterials = Material::query()
            ->where('product_id', $product->id)
            ->orderBy('order')
            ->get(['id', 'parent_id', 'title', 'pdf_path']);

        $representativeId = $allMaterials->firstWhere('parent_id', null)?->id;
        $summary = $representativeId !== null
            ? $this->ratingSummaries->findForTarget(ReviewTargetType::Material, $representativeId)
            : null;

        $owned = $this->ownedProductIds($viewer, [$product->id])->contains($product->id);

        return [
            'material' => $product,
            'toc' => $this->buildTocTree($allMaterials, null),
            'ratingAverage' => $summary?->avg_rating !== null ? (float) $summary->avg_rating : null,
            'ratingCount' => $summary->review_count ?? 0,
            'owned' => $owned,
            // Dùng đúng 1 hàm coverUrl() — y hệt ảnh đã hiện ở thẻ danh sách (mapCard()) —
            // để trang chi tiết KHÔNG BAO GIỜ lệch ảnh bìa so với thẻ ngoài danh sách nữa.
            'coverUrl' => $this->coverUrl($product),
        ];
    }

    /**
     * Dựng cây Mục lục đa cấp từ danh sách Material PHẲNG (TẤT CẢ Material của 1 Product,
     * không lọc parent_id, đã orderBy('order')) — nhóm theo parent_id rồi đệ quy xuống từng
     * cấp con. 'hasContent' — chỉ bài đã có PDF mới có gì để đọc. Blade dùng cờ này CÙNG VỚI
     * 'owned' để quyết định 1 dòng mục lục (ở BẤT KỲ cấp nào) có bấm vào đọc được không (đủ 2
     * điều kiện: đã mua VÀ bài đó có nội dung) — xem partials.materials-toc-item.blade.php.
     * Quyền đọc THẬT vẫn luôn được App\Services\AccessGateService kiểm tra lại ở route đọc,
     * đây chỉ là hiển thị.
     *
     * @param  Collection<int, Material>  $materials
     * @return array<int, array{id:int,title:string,hasContent:bool,children:array}>
     */
    private function buildTocTree(Collection $materials, ?int $parentId): array
    {
        return $materials
            ->where('parent_id', $parentId)
            ->map(fn (Material $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'hasContent' => $m->pdf_path !== null,
                'children' => $this->buildTocTree($materials, $m->id),
            ])
            ->values()
            ->all();
    }

    /**
     * Trang chủ (PUB-01/02, 12.1) — "Tài liệu nổi bật": MỚI PHÁT HÀNH NHẤT gộp chung CẢ 3
     * loại (Sách/Chuyên đề/Đề thi), khác với materials.index vốn luôn lọc riêng theo TỪNG
     * tab. Tái dùng đúng baseQuery()/mapCard() để không lặp lại luật lọc (chỉ
     * published+public, không lộ loại "course") ở một chỗ thứ hai. Không tính "Tic xanh" ở
     * đây (khối chỉ là teaser dẫn sang materials.index/show — nơi có tick thật).
     */
    public function featuredData(int $limit = 4): array
    {
        $products = $this->baseQuery()->latest()->limit($limit)->get();

        $representativeIdByProductId = $this->representativeMaterialIds($products->pluck('id')->all());
        $ratingsByMaterialId = $this->ratingSummariesByMaterialId($representativeIdByProductId->values()->all());

        return $products->map(
            fn (Product $p) => $this->mapCard($p, $representativeIdByProductId->get($p->id), $ratingsByMaterialId, collect())
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

    private function mapCard(Product $product, ?int $representativeMaterialId, Collection $ratingsByMaterialId, Collection $ownedProductIds): array
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
            'owned' => $ownedProductIds->contains($product->id),
            'image' => $this->coverUrl($product),
        ];
    }

    /**
     * Ảnh bìa 1 sản phẩm — dùng CHUNG bởi cả thẻ danh sách (mapCard) lẫn trang chi tiết
     * (showData), để 2 nơi luôn hiện đúng 1 ảnh giống nhau, không lệch nhau như trước đây
     * (khi mỗi nơi tự vẽ ảnh/placeholder riêng theo 2 cách khác nhau).
     *
     * Có ảnh bìa thật (admin đã tải lên qua Admin\ProductController) → dùng đúng ảnh đó,
     * cùng cách lấy URL với resources/views/admin/products/edit.blade.php
     * (asset('storage/'.cover_image_path)). Chưa có ảnh thật → tự vẽ 1 ảnh bìa SVG ngay
     * trên server (gradient thương hiệu + tên tài liệu), không phụ thuộc dịch vụ ảnh ngoài
     * (trước đây trang danh sách dùng picsum.photos — ảnh ngẫu nhiên không liên quan nội
     * dung, còn trang chi tiết lại tự vẽ 1 kiểu placeholder khác — đây chính là nguyên nhân
     * "ảnh thumbnail không khớp với ảnh ngoài" đã được báo).
     */
    private function coverUrl(Product $product): string
    {
        return $product->cover_image_path
            ? asset('storage/'.$product->cover_image_path)
            : $this->placeholderCoverDataUri($product->title);
    }

    /** Bìa tạm dạng SVG (data URI) khi sản phẩm chưa có ảnh bìa thật — màu gradient chọn ổn định theo tiêu đề (không đổi mỗi lần tải lại trang), tái dùng đúng bảng màu thương hiệu hiện có. */
    private function placeholderCoverDataUri(string $title): string
    {
        $palettes = [
            ['#f43f5e', '#be123c'],
            ['#f59e0b', '#ea580c'],
            ['#0ea5e9', '#1d4ed8'],
            ['#10b981', '#0f766e'],
            ['#8b5cf6', '#7e22ce'],
        ];
        [$from, $to] = $palettes[crc32($title) % count($palettes)];

        $lines = $this->wrapTitle($title, 16, 4);
        $lineHeight = 30;
        $startY = 250 - (count($lines) - 1) * $lineHeight / 2;

        $tspans = collect($lines)->map(fn ($line, $i) => sprintf(
            '<tspan x="200" y="%d">%s</tspan>',
            (int) round($startY + $i * $lineHeight),
            htmlspecialchars($line, ENT_QUOTES | ENT_XML1)
        ))->implode('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
            .'<stop offset="0" stop-color="'.$from.'"/><stop offset="1" stop-color="'.$to.'"/>'
            .'</linearGradient></defs>'
            .'<rect width="400" height="400" fill="url(#g)"/>'
            .'<text x="200" y="150" font-size="56" text-anchor="middle">📘</text>'
            .'<text font-family="system-ui, -apple-system, Segoe UI, sans-serif" font-size="24" font-weight="600" fill="#ffffff" text-anchor="middle">'.$tspans.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Bẻ tiêu đề thành tối đa $maxLines dòng (~$maxChars ký tự/dòng) để vẽ trong SVG — SVG không tự xuống dòng như CSS line-clamp. */
    private function wrapTitle(string $title, int $maxChars, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($title)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($current === '' || mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;

                continue;
            }
            $lines[] = $current;
            $current = $word;
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = rtrim($lines[$maxLines - 1]);
            $lines[$maxLines - 1] = (mb_strlen($last) > $maxChars - 1 ? mb_substr($last, 0, $maxChars - 1) : $last).'…';
        }

        return $lines;
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

    /**
     * "Tic xanh" (4.1) — product_id mà $viewer đang có quyền học cá nhân CÒN HIỆU LỰC
     * (scope=personal_learning, không tính teacher_teaching — đó là quyền dạy 1 khóa học,
     * khác phạm vi trang Tài liệu này). Khách ($viewer=null) luôn trả về rỗng.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, int>
     */
    private function ownedProductIds(?User $viewer, array $productIds): Collection
    {
        if ($viewer === null || $productIds === []) {
            return collect();
        }

        return $this->accessRights->forUserWithProduct($viewer->id)
            ->filter(fn (AccessRight $ar) => $ar->scope === AccessScope::PersonalLearning
                && in_array($ar->product_id, $productIds, true)
                && $ar->isCurrentlyActive())
            ->pluck('product_id')
            ->unique()
            ->values();
    }
}
