<?php

namespace App\Services\Public;

use App\Enums\AccessRightStatus;
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
use App\Services\Access\AccessService;
use App\Services\AccessGateService;
use App\Support\ProductCover;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;


class MaterialService
{
    private const TABS = [
        'sach' => ProductType::Book,
        'chuyen-de' => ProductType::Topic,
        'de-thi' => ProductType::Exam,
    ];

    /** Khoá thể loại của bộ lọc (bản mẫu: books/topics/exams) <- loại sản phẩm. */
    private const CATEGORY_BY_TYPE = ['book' => 'books', 'topic' => 'topics', 'exam' => 'exams'];

    /** ?tab=... cũ (sach / chuyen-de / de-thi) -> thể loại ban đầu của bộ lọc. */
    private const TAB_TO_CATEGORY = ['sach' => 'books', 'chuyen-de' => 'topics', 'de-thi' => 'exams'];

    public function __construct(
        private ProductRepositoryInterface $products,
        private RatingSummaryRepositoryInterface $ratingSummaries,
        private AccessRightRepositoryInterface $accessRights,
        private AccessGateService $accessGate,
        private MaterialAssignmentService $assignments,
    ) {}

    /**
     * SỬA 3/10 (khách: "click vào đọc ngay ngoài trang tài liệu public, local vào được mà
     * server không vào được") — ĐƯỜNG DẪN của nút "Vào đọc ngay".
     *
     * LỖI CŨ: chỉ trả về trang đọc khi sản phẩm có CHƯƠNG kèm PDF. Sản phẩm nào để nội dung ở
     * MỘT tệp PDF gắn thẳng (products.content_pdf_path, không chia chương) thì trả null, và
     * Blade rơi về... danh sách Tài liệu của tôi. Người dùng bấm "Vào đọc ngay" mà ra trang
     * danh sách, tưởng hỏng. Trên máy của người làm thì sản phẩm có chia chương nên không ai
     * thấy, lên máy thật mới lộ.
     *
     * Luật giống hệt LibraryService::readHref — một luật, hai nơi gọi, không có bản thứ hai để
     * lệch nhau.
     *
     * CỐ Ý KHÔNG TRUY VẤN trong hàm này: trang danh sách gọi nó cho TỪNG thẻ sản phẩm, hỏi cơ
     * sở dữ liệu ở đây là đẻ ra N+1 ngay giữa một trang công khai. Id chương đầu tiên đã có sẵn
     * từ truy vấn gộp firstReadableMaterialIds(), còn content_pdf_path là cột của chính bản ghi
     * đang cầm trên tay.
     */
    private function readHrefFor(Product $product, ?int $firstReadableMaterialId, ?string $readRoutePrefix): ?string
    {
        return match (true) {
            $readRoutePrefix === null => null,
            $firstReadableMaterialId !== null => route($readRoutePrefix.'.materials.read', $firstReadableMaterialId),
            // Không có chương nào nhưng sản phẩm vẫn có nội dung đọc được (một tệp PDF gắn
            // thẳng) -> mở màn đọc liền mạch.
            filled($product->content_pdf_path) => route($readRoutePrefix.'.products.read', $product->id),
            // SỬA 14/10 (khách: "admin chưa mua cũng full quyền đọc toàn bộ") — admin LUÔN có nút Đọc tài liệu,
            // kể cả tài liệu chưa có chương/PDF (màn đọc liền mạch tự báo "chưa có nội dung" + link quản lý).
            $readRoutePrefix === 'admin' => route('admin.products.read', $product->id),
            default => null,
        };
    }

    /**
     * SỬA 9/10 (khách: "check UI source mới trang tài liệu public, update lại toàn bộ UI, check kỹ quyền
     * khách vãng lai / học sinh / giáo viên / admin, cả giao tài liệu") — dựng lại theo
     * education-main/src/components/MaterialsPage.jsx.
     *
     * Khác bản cũ: không còn 3 tab theo loại mà là THANH LỌC (thể loại, độ khó, giá, quyền sử dụng, sắp
     * xếp) + các "không gian" theo vai trò:
     *   · Kho tài liệu       mọi người (kể cả khách vãng lai);
     *   · Tài liệu của tôi   học sinh, giáo viên (những gì đã có quyền / đã hết hạn);
     *   · Tài liệu được giao học sinh;
     *   · Tài liệu đã giao   giáo viên (lượt mình giao), admin (tất cả).
     * Toàn bộ dữ liệu nạp một lần, lọc/sắp xếp/phân trang chạy phía trình duyệt (Alpine).
     */
    public function indexData(string $tab, ?User $viewer = null): array
    {
        $allProducts = $this->baseQuery()
            ->whereIn('type', array_map(fn ($t) => $t->value, array_values(self::TABS)))
            ->with('owner:id,name')
            // SỬA 16/10 — cùng thứ tự với danh sách Tài liệu admin (nút ↑/↓), thay cho "mới nhất trước".
            ->ordered()
            ->limit(120)
            ->get();

        $productIds = $allProducts->pluck('id')->all();
        $representativeIdByProductId = $this->representativeMaterialIds($productIds);
        $ratingsByMaterialId = $this->ratingSummariesByMaterialId($representativeIdByProductId->values()->all());
        $accessByProductId = $this->accessInfoByProduct($viewer, $productIds);
        $ownedProductIds = $accessByProductId->filter(fn ($a) => $a['owned'])->keys();
        $pageCounts = $this->materialCounts($productIds);
        $firstReadable = $this->firstReadableMaterialIds($productIds);
        $readRoutePrefix = $this->readRoutePrefixFor($viewer);

        $cards = $allProducts->map(
            fn (Product $p) => $this->mapCard($p, $representativeIdByProductId->get($p->id), $ratingsByMaterialId, $ownedProductIds, $pageCounts, $firstReadable->get($p->id), $readRoutePrefix, $accessByProductId->get($p->id))
        )->values();

        $scope = $this->assignments->scopeFor($viewer);

        // Lượt giao: học sinh xem lượt được giao cho mình, giáo viên/admin xem lượt đã giao.
        $assignedRows = $scope['canViewAssigned'] && $viewer !== null ? array_values($this->assignments->forStudent($viewer)) : [];
        $managedRows = $scope['canManage'] && $viewer !== null ? $this->assignments->managed($viewer) : [];

        // Tài liệu trong lượt giao có thể đã gỡ khỏi kho (ẩn/lưu trữ): vẫn phải hiện được dòng giao,
        // nên nạp thêm thẻ cho những tài liệu đó (đánh dấu inCatalog = false -> không lên Kho tài liệu).
        $listedIds = $cards->pluck('id')->all();
        $missingIds = collect($assignedRows)->merge($managedRows)->pluck('productId')->unique()
            ->reject(fn ($id) => in_array($id, $listedIds, true))->values()->all();
        $extraCards = $missingIds === [] ? collect() : $this->unlistedCards($missingIds, $viewer, $readRoutePrefix);

        $assignedIds = collect($assignedRows)->pluck('productId')->all();

        $rows = $cards->map(fn (array $c) => $c + ['inCatalog' => true])
            ->concat($extraCards->map(fn (array $c) => $c + ['inCatalog' => false]))
            ->values()
            ->all();

        return [
            'cards' => $cards->all(),
            'rows' => $rows,
            'scope' => $scope,
            'assignedRows' => $assignedRows,
            'managedRows' => $managedRows,
            'assignedProductIds' => $assignedIds,
            'activeCategory' => self::TAB_TO_CATEGORY[$tab] ?? 'all',
            'accessDaysOptions' => MaterialAssignmentService::ACCESS_DAYS,
        ];
    }

    /**
     * Thẻ cho những tài liệu có lượt giao nhưng KHÔNG còn nằm trong kho công khai.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    private function unlistedCards(array $ids, ?User $viewer, ?string $readRoutePrefix): Collection
    {
        $products = Product::query()->whereIn('id', $ids)->with('owner:id,name')->get();

        if ($products->isEmpty()) {
            return collect();
        }

        $productIds = $products->pluck('id')->all();
        $accessByProductId = $this->accessInfoByProduct($viewer, $productIds);
        $ownedProductIds = $accessByProductId->filter(fn ($a) => $a['owned'])->keys();
        $representativeIdByProductId = $this->representativeMaterialIds($productIds);
        $ratingsByMaterialId = $this->ratingSummariesByMaterialId($representativeIdByProductId->values()->all());
        $firstReadable = $this->firstReadableMaterialIds($productIds);
        $pageCounts = $this->materialCounts($productIds);

        return $products->map(
            fn (Product $p) => $this->mapCard($p, $representativeIdByProductId->get($p->id), $ratingsByMaterialId, $ownedProductIds, $pageCounts, $firstReadable->get($p->id), $readRoutePrefix, $accessByProductId->get($p->id))
        )->values();
    }

    /**
     * Quyền sử dụng của người xem với từng tài liệu: còn hạn / đã hết hạn / chưa có, kèm số ngày còn lại
     * (bản mẫu: getMaterialAccess + AccessBadge). Nguồn quyền: mua/kích hoạt mã, được giáo viên giao
     * (source = 'assignment'), hoặc được lớp cấp (giáo viên gắn nguyên tài liệu vào lớp).
     *
     * expires_at = NULL nghĩa là quyền VĨNH VIỄN (xem AccessRight::isCurrentlyActive()).
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, array{owned: bool, expired: bool, remainingDays: ?int, expiresAt: ?string, source: ?string}> keyed theo product_id
     */
    private function accessInfoByProduct(?User $viewer, array $productIds): Collection
    {
        if ($viewer === null || $productIds === []) {
            return collect();
        }

        $now = now();
        $best = []; // product_id => ['expires' => ?Carbon, 'permanent' => bool, 'source' => string]
        $lapsed = []; // product_id => Carbon (hạn gần nhất đã qua)

        $rights = AccessRight::query()
            ->where('user_id', $viewer->id)
            ->whereIn('product_id', $productIds)
            ->whereIn('scope', [AccessScope::PersonalLearning->value, AccessScope::TeacherTeaching->value])
            ->whereIn('status', [AccessRightStatus::Active->value, AccessRightStatus::Expired->value])
            ->get();

        foreach ($rights as $right) {
            $pid = (int) $right->product_id;
            $source = $right->source === MaterialAssignmentService::SOURCE ? 'assignment' : 'activation';

            if ($right->status === AccessRightStatus::Active && ($right->expires_at === null || $right->expires_at->gt($now))) {
                $current = $best[$pid] ?? null;
                $isLonger = $current === null
                    || (! $current['permanent'] && ($right->expires_at === null || $right->expires_at->gt($current['expires'])));

                if ($isLonger) {
                    $best[$pid] = ['expires' => $right->expires_at, 'permanent' => $right->expires_at === null, 'source' => $source];
                }
            } else {
                $at = $right->expires_at ?? $now;
                if (! isset($lapsed[$pid]) || $at->gt($lapsed[$pid])) {
                    $lapsed[$pid] = $at;
                }
            }
        }

        // Được lớp cấp quyền (gắn nguyên tài liệu vào lớp học sinh đang học): đọc được, không có hạn riêng.
        try {
            foreach ($this->accessGate->classGrantedProducts($viewer) as $granted) {
                if (in_array($granted->id, $productIds, true) && ! isset($best[$granted->id])) {
                    $best[$granted->id] = ['expires' => null, 'permanent' => true, 'source' => 'class'];
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $info = collect();

        foreach ($productIds as $pid) {
            if (isset($best[$pid])) {
                $b = $best[$pid];
                $info->put($pid, [
                    'owned' => true,
                    'expired' => false,
                    'remainingDays' => $b['permanent'] ? null : max(0, (int) ceil(($b['expires']->getTimestamp() - $now->getTimestamp()) / 86400)),
                    'expiresAt' => $b['permanent'] ? null : $b['expires']->format('d/m/Y H:i'),
                    'source' => $b['source'],
                ]);
            } elseif (isset($lapsed[$pid])) {
                $info->put($pid, [
                    'owned' => false,
                    'expired' => true,
                    'remainingDays' => 0,
                    'expiresAt' => $lapsed[$pid]->format('d/m/Y H:i'),
                    'source' => null,
                ]);
            }
        }

        return $info;
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

        return [
            'material' => $product,
            'toc' => $this->buildTocTree($allMaterials, null),
            'readHref' => $this->readHrefFor($product, $firstReadableId, $this->readRoutePrefixFor($viewer)),
            'ratingAverage' => $this->combinedRating($product, $summary)['avg'] ?? null,
            'ratingCount' => $this->combinedRating($product, $summary)['count'] ?? 0,
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

        $products = $query->ordered()->limit($limit)->get(); // SỬA 16/10 — thứ tự chỉnh tay như admin.

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

    /**
     * @param  array{owned: bool, expired: bool, remainingDays: ?int, expiresAt: ?string, source: ?string}|null  $access
     */
    private function mapCard(Product $product, ?int $representativeMaterialId, Collection $ratingsByMaterialId, Collection $ownedProductIds, ?Collection $pageCounts = null, ?int $firstReadableMaterialId = null, ?string $readRoutePrefix = null, ?array $access = null): array
    {
        $summary = $representativeMaterialId !== null ? $ratingsByMaterialId->get($representativeMaterialId) : null;

        $typeValue = $product->type->value ?? (string) $product->type;
        $price = (int) $product->price;
        $priceLabel = $price > 0 ? $this->money($price) : 'Miễn phí';
        if ($product->has_print_option) {
            $priceLabel .= ' · Có bản in';
        }

        [$badgeLabel, $badgeTone] = $price > 0 ? ['Cần kích hoạt', 'warning'] : ['Công khai', 'info'];

        $tagLabel = $product->topic ?: ($product->grade ? 'Dành cho '.$product->grade : ($product->subject ?: 'Học liệu'));

        $unitCount = (int) ($pageCounts?->get($product->id) ?? 0);
        $unitLabel = match ($typeValue) {
            'exam' => $unitCount > 0 ? $unitCount.' đề' : 'Đang cập nhật',
            'topic' => $unitCount > 0 ? $unitCount.' phần' : 'Đang cập nhật',
            default => $unitCount > 0 ? $unitCount.' chương' : 'Đang cập nhật',
        };

        // SỬA 24/10 (khách: "thông tin bộ đề mô tả rõ: là bộ đề, khối nào, mục đích, có bao nhiêu đề") — chỉ loại Bộ đề.
        $bundle = $typeValue === 'exam' ? $this->bundleSummary($product) : null;
        if ($bundle !== null) {
            $unitLabel = $bundle['exams'] > 0
                ? $bundle['exams'].' đề'.($bundle['questions'] > 0 ? ' · '.$bundle['questions'].' bài' : '')
                : 'Đang cập nhật đề';
        }

        // Đánh giá hiển thị = đánh giá nhập tay (admin) GỘP đánh giá thật của người đọc — cùng cách
        // làm với đề thi (PracticeService::combinedRating).
        $rating = $this->combinedRating($product, $summary);

        // Bản in: giá bản mềm + giá bản in riêng của tài liệu (SỬA 14/10 — trước là phụ phí cố định).
        $pricePrint = $product->has_print_option && $price > 0 ? $price + $product->printPrice() : null;

        $title = (string) $product->title;
        $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $product->description)) ?? '');
        $author = trim((string) ($product->author_name ?? '')) ?: ($product->owner?->name ?: 'Tổ chuyên môn Ôn Thi 360');
        $difficulty = is_numeric($product->difficulty_level ?? null) && (int) $product->difficulty_level >= 1 && (int) $product->difficulty_level <= 5
            ? (int) $product->difficulty_level
            : null;

        $accessInfo = $access ?? [
            'owned' => $ownedProductIds->contains($product->id),
            'expired' => false,
            'remainingDays' => null,
            'expiresAt' => null,
            'source' => null,
        ];

        return [
            'id' => $product->id,
            'type' => $typeValue,
            'category' => self::CATEGORY_BY_TYPE[$typeValue] ?? 'books',
            'title' => $title,
            'meta' => $priceLabel,
            'average' => $rating['avg'] ?? null,
            'count' => $rating['count'] ?? 0,
            'difficultyLevel' => $difficulty,
            'badge' => $badgeLabel,
            'tone' => $badgeTone,
            'owned' => (bool) $accessInfo['owned'],
            'expired' => (bool) $accessInfo['expired'],
            'remainingDays' => $accessInfo['remainingDays'],
            'expiresAt' => $accessInfo['expiresAt'],
            'accessSource' => $accessInfo['source'],
            'image' => $this->coverUrl($product),
            'tag' => $tagLabel,
            'unitLabel' => $unitLabel,
            'bundle' => $bundle,
            'highlight' => $product->description,
            'author' => $author,
            'priceSoft' => $price > 0 ? $this->money($price) : 'Miễn phí',
            'priceValue' => $price,
            'pricePrint' => $pricePrint !== null ? $this->money($pricePrint) : null,
            'hasPrintOption' => (bool) $product->has_print_option,
            'durationMonths' => $product->duration_months,
            // Tìm KHÔNG DẤU như bản mẫu (normalizeMaterialSearch): "quy hoach dong" khớp "Quy hoạch động".
            'search' => Str::lower(Str::ascii($title.' '.$author.' '.$tagLabel.' '.$description)),
            'href' => route('materials.show', $product->id),
            'checkoutHref' => route('access.checkout', $product->id),
            // SỬA 28/10 (khách: "bộ đề đã mua để nút xanh lá như sách, đổi chữ là Làm bộ đề") — bộ đề không có chương / PDF đọc
            // liền mạch thì nút "Làm bộ đề" mở trang bộ đề (danh sách đề để làm), thay cho nút "Xem thông tin".
            'readHref' => $this->readHrefFor($product, $firstReadableMaterialId, $readRoutePrefix)
                ?? ($typeValue === 'exam' && $readRoutePrefix !== null ? route('bundles.show', $product->id) : null),
        ];
    }

    private ?\App\Services\ExamBundle\ExamBundleService $bundleService = null;

    /**
     * SỬA 24/10 — tóm tắt Bộ đề cho thẻ / hộp thông tin ở Kho tài liệu: số đề, số bài, khối, kỳ thi, tỉnh/thành, năm học,
     * mục đích. Tính từ danh sách đề THẬT của bộ (ExamBundleService::items() — đề luyện tập đã phát hành, nhớ trong request).
     *
     * @return array{exams: int, questions: int, level: ?string, contest: ?string, province: ?string, provinces: list<string>, years: ?string, purpose: string, note: ?string}
     */
    private function bundleSummary(Product $product): array
    {
        $this->bundleService ??= app(\App\Services\ExamBundle\ExamBundleService::class);
        $items = $this->bundleService->items($product);
        $uniq = fn (callable $pick) => $items->map($pick)->filter()->unique()->values()->all();

        $contests = $uniq(fn ($a) => $a->examCategoryLabel());
        $levels = $uniq(fn ($a) => $a->examLevelLabel());
        $provinces = $uniq(fn ($a) => $a->provinceLabel());
        $years = $items->map(fn ($a) => \App\Support\ExamBundleFilters::yearOf($a->academic_year))->filter()->sort()->values();
        $questions = (int) $items->sum(fn ($a) => (int) ($a->items_count ?? 0) > 0
            ? (int) $a->items_count
            : (int) ($a->answer_keys_count ?? 0) + (int) ($a->coding_items_count ?? 0));

        $grade = trim((string) ($product->grade ?? ''));
        $contest = $contests !== [] ? implode(', ', $contests) : null;
        $province = match (true) {
            $provinces === [] => null,
            count($provinces) <= 3 => implode(', ', $provinces),
            default => count($provinces).' tỉnh/thành',
        };
        $schoolYear = static fn (int $y) => $y.'-'.($y + 1);
        // SỬA 24/10 (khách: "Từ năm học ... đến năm học ...") — 1 năm thì chỉ ghi năm đó.
        $yearFrom = $years->isEmpty() ? null : $schoolYear($years->first());
        $yearTo = $years->isEmpty() || $years->first() === $years->last() ? null : $schoolYear($years->last());
        $yearsLabel = $yearFrom === null ? null : ($yearTo === null ? $yearFrom : 'Từ '.$yearFrom.' đến '.$yearTo);
        // SỬA 24/10 (khách: "thống kê bao nhiêu đề, năm nào đến năm nào") — số đề theo từng năm học, năm mới trước.
        $byYear = $years->countBy()->sortKeysDesc()->map(fn ($n, $y) => ['year' => $schoolYear((int) $y), 'count' => (int) $n])->values()->all();
        $undated = $items->count() - $years->count();

        $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $product->description)) ?? '');
        $sameAsTitle = Str::lower(Str::ascii($description)) === Str::lower(Str::ascii((string) $product->title));

        return [
            'exams' => $items->count(),
            'questions' => $questions,
            'level' => implode(' · ', array_filter([implode(', ', $levels), $grade])) ?: null,
            'contest' => $contest,
            'province' => $province,
            'provinces' => $provinces,   // ô lọc "Tỉnh/thành" khi xem thể loại Bộ đề
            'years' => $yearsLabel,
            'yearFrom' => $yearFrom,
            'yearTo' => $yearTo,
            'byYear' => $byYear,          // [['year' => '2024-2025', 'count' => 3], …]
            'yearCount' => count($byYear),
            'undated' => $undated,        // số đề chưa ghi năm học
            'purpose' => 'Ôn luyện '.($contest !== null ? 'kỳ thi '.$contest : 'thi học sinh giỏi').(count($provinces) === 1 ? ' ('.$provinces[0].')' : '')
                .' bằng đề thi thật — làm từng đề trực tuyến, chấm tự động, xem điểm từng bài.',
            'note' => $description !== '' && ! $sameAsTitle ? Str::limit($description, 140) : null,
        ];
    }

    /** SỬA 18/10 — giá hiển thị bằng TOKEN (200.000đ -> "200 token"), xem App\Support\TokenPrice. */
    private function money(int $amount): string
    {
        return \App\Support\TokenPrice::label($amount);
    }

    /**
     * Đánh giá nhập tay (products.rating_score/rating_count) gộp trung bình có trọng số với đánh giá
     * thật (rating_summaries). Chưa có gì -> null (thẻ hiện "Chưa có đánh giá").
     *
     * @return array{avg: float, count: int}|null
     */
    private function combinedRating(Product $product, ?RatingSummary $real): ?array
    {
        $realCount = (int) ($real->review_count ?? 0);
        $realAvg = $real?->avg_rating !== null ? (float) $real->avg_rating : null;
        $manualCount = (int) ($product->rating_count ?? 0);
        $manualScore = $product->rating_score ?? null;

        if ($manualScore === null || $manualCount < 1) {
            return $realCount > 0 && $realAvg !== null ? ['avg' => round($realAvg, 1), 'count' => $realCount] : null;
        }

        $total = $manualCount + $realCount;
        $sum = (float) $manualScore * $manualCount + ($realCount > 0 && $realAvg !== null ? $realAvg * $realCount : 0.0);

        return ['avg' => round($sum / $total, 1), 'count' => $total];
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
            // SỬA 15/10 — mở chương ĐẦU TIÊN theo thứ tự chương (chương trước học liệu con).
            ->orderByRaw("CASE WHEN type = 'chapter' THEN 0 ELSE 1 END")
            ->orderBy('order')
            ->orderBy('id')
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
            // SỬA 14/10 — admin đọc được mọi tài liệu (khách yêu cầu), xem Admin\MaterialReadController.
            $viewer->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN) => 'admin',
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
        return ProductCover::url($product->cover_image_path)
            ?? $this->placeholderCoverDataUri($product->title);
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
