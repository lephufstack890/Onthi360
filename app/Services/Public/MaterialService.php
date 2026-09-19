<?php

namespace App\Services\Public;

use App\Enums\AccessScope;
use App\Enums\ContentStatus;
use App\Enums\ProductType;
use App\Enums\ReviewTargetType;
use App\Models\AccessRight;
use App\Models\Material;
use App\Models\Product;
use App\Models\RatingSummary;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use Illuminate\Support\Collection;


class MaterialService
{
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

        $allProducts = $this->baseQuery()
            ->whereIn('type', array_map(fn ($t) => $t->value, array_values(self::TABS)))
            ->latest()
            ->limit(120)
            ->get();

        $productIds = $allProducts->pluck('id')->all();
        $representativeIdByProductId = $this->representativeMaterialIds($productIds);
        $ratingsByMaterialId = $this->ratingSummariesByMaterialId($representativeIdByProductId->values()->all());
        $ownedProductIds = $this->ownedProductIds($viewer, $productIds);
        $pageCounts = $this->materialCounts($productIds);
        $firstReadable = $this->firstReadableMaterialIds($productIds);
        $readRoutePrefix = $this->readRoutePrefixFor($viewer);

        $cards = $allProducts->map(
            fn (Product $p) => $this->mapCard($p, $representativeIdByProductId->get($p->id), $ratingsByMaterialId, $ownedProductIds, $pageCounts, $firstReadable->get($p->id), $readRoutePrefix)
        );

        $groups = [];
        foreach (self::TABS as $key => $productType) {
            $groups[$key] = $cards->where('type', $productType->value)->values()->all();
        }

        return [
            'tabs' => $tabs,
            'materials' => $groups[$tab] ?? $groups['sach'],
            'materialGroups' => $groups,
            'activeTab' => $tab,
        ];
    }

    public function showData(int $productId, ?User $viewer = null): array
    {
        $product = $this->baseQuery()->findOrFail($productId);

        $allMaterials = Material::query()
            ->where('product_id', $product->id)
            ->orderBy('order')
            ->get(['id', 'parent_id', 'title', 'pdf_path', 'status']);

        $representativeId = $allMaterials->firstWhere('parent_id', null)?->id;
        $summary = $representativeId !== null
            ? $this->ratingSummaries->findForTarget(ReviewTargetType::Material, $representativeId)
            : null;

        $owned = $this->ownedProductIds($viewer, [$product->id])->contains($product->id);

        $firstReadableId = $allMaterials
            ->first(fn (Material $m) => $m->status === ContentStatus::Published && filled($m->pdf_path))?->id;
        $readRoutePrefix = $this->readRoutePrefixFor($viewer);

        return [
            'material' => $product,
            'toc' => $this->buildTocTree($allMaterials, null),
            'readHref' => ($firstReadableId !== null && $readRoutePrefix !== null)
                ? route($readRoutePrefix.'.materials.read', $firstReadableId)
                : null,
            'ratingAverage' => $summary?->avg_rating !== null ? (float) $summary->avg_rating : null,
            'ratingCount' => $summary->review_count ?? 0,
            'owned' => $owned,
            'coverUrl' => $this->coverUrl($product),
        ];
    }

    /**
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

    public function featuredData(int $limit = 4, ?ProductType $type = null): array
    {
        $query = $this->baseQuery();

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        $products = $query->latest()->limit($limit)->get();

        $representativeIdByProductId = $this->representativeMaterialIds($products->pluck('id')->all());
        $ratingsByMaterialId = $this->ratingSummariesByMaterialId($representativeIdByProductId->values()->all());

        return $products->map(
            fn (Product $p) => $this->mapCard($p, $representativeIdByProductId->get($p->id), $ratingsByMaterialId, collect(), collect())
        )->all();
    }

    private function baseQuery()
    {
        return $this->products->query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->where('type', '!=', ProductType::Course->value);
    }

    private function mapCard(Product $product, ?int $representativeMaterialId, Collection $ratingsByMaterialId, Collection $ownedProductIds, ?Collection $pageCounts = null, ?int $firstReadableMaterialId = null, ?string $readRoutePrefix = null): array
    {
        $summary = $representativeMaterialId !== null ? $ratingsByMaterialId->get($representativeMaterialId) : null;

        $priceLabel = $product->price > 0 ? number_format($product->price).'đ' : 'Miễn phí';
        if ($product->has_print_option) {
            $priceLabel .= ' · Có bản in';
        }

        [$badgeLabel, $badgeTone] = $product->price > 0 ? ['Cần kích hoạt', 'warning'] : ['Công khai', 'info'];

        $tagLabel = $product->topic ?: ($product->grade ? 'Dành cho '.$product->grade : ($product->subject ?: 'Học liệu'));

        $unitCount = (int) ($pageCounts?->get($product->id) ?? 0);
        $unitLabel = match ($product->type->value ?? (string) $product->type) {
            'exam' => $unitCount > 0 ? $unitCount.' đề' : 'Đang cập nhật',
            'topic' => $unitCount > 0 ? $unitCount.' phần' : 'Đang cập nhật',
            default => $unitCount > 0 ? $unitCount.' chương' : 'Đang cập nhật',
        };

        return [
            'id' => $product->id,
            'type' => $product->type->value ?? (string) $product->type,
            'title' => $product->title,
            'meta' => $priceLabel,
            'average' => $summary?->avg_rating !== null ? (float) $summary->avg_rating : null,
            'count' => $summary->review_count ?? 0,
            'badge' => $badgeLabel,
            'tone' => $badgeTone,
            'owned' => $ownedProductIds->contains($product->id),
            'image' => $this->coverUrl($product),
            'tag' => $tagLabel,
            'unitLabel' => $unitLabel,
            'highlight' => $product->description,
            'author' => $product->owner?->name ?: 'Tổ chuyên môn Ôn Thi 360',
            'priceSoft' => $product->price > 0 ? number_format($product->price).'đ' : 'Miễn phí',
            'hasPrintOption' => (bool) $product->has_print_option,
            'durationMonths' => $product->duration_months,
            'href' => route('materials.show', $product->id),
            'checkoutHref' => route('access.checkout', $product->id),
            'readHref' => ($firstReadableMaterialId !== null && $readRoutePrefix !== null)
                ? route($readRoutePrefix.'.materials.read', $firstReadableMaterialId)
                : null,
        ];
    }

    /**
     * @param  array<int, int>  $productIds
     * @return Collection<int, int> keyed theo product_id
     */
    private function firstReadableMaterialIds(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return \App\Models\Material::query()
            ->whereIn('product_id', $productIds)
            ->where('status', 'published')
            ->whereNotNull('pdf_path')
            ->orderBy('product_id')
            ->orderBy('order')
            ->get(['id', 'product_id'])
            ->groupBy('product_id')
            ->map(fn ($group) => (int) $group->first()->id);
    }

    private function readRoutePrefixFor(?User $viewer): ?string
    {
        if ($viewer === null) {
            return null;
        }

        return match (true) {
            $viewer->hasRole(Role::STUDENT) => 'student',
            $viewer->hasRole(Role::TEACHER) => 'teacher',
            default => null,
        };
    }

    /**
     * @param  array<int, int>  $productIds
     * @return Collection<int, int> keyed theo product_id
     */
    private function materialCounts(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return Material::query()
            ->selectRaw('product_id, COUNT(*) as total')
            ->whereIn('product_id', $productIds)
            ->whereNull('parent_id')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');
    }

    private function coverUrl(Product $product): string
    {
        return $product->cover_image_path
            ? asset('storage/'.$product->cover_image_path)
            : $this->placeholderCoverDataUri($product->title);
    }

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
     * @param  array<int, int>  $productIds
     * @return Collection<int, int> keyed theo product_id, giá trị là material_id đại diện.
     */
    private function representativeMaterialIds(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

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
     * @param  array<int, int>  $productIds
     * @return Collection<int, int>
     */
    private function ownedProductIds(?User $viewer, array $productIds): Collection
    {
        if ($viewer === null || $productIds === []) {
            return collect();
        }

        return $this->accessRights->forUserWithProduct($viewer->id)
            ->filter(fn (AccessRight $ar) => in_array($ar->scope, [AccessScope::PersonalLearning, AccessScope::TeacherTeaching], true)
                && in_array($ar->product_id, $productIds, true)
                && $ar->isCurrentlyActive())
            ->pluck('product_id')
            ->unique()
            ->values();
    }
}
