<?php

namespace App\Services\Admin;

use App\Models\Assessment;
use App\Models\Product;
use App\Models\Question;
use App\Services\ExamBundle\ExamBundleService;
use App\Support\QuestionDifficulty;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * SỬA 27/10 (khách: "thêm tab Bài của tài liệu trong Kho bài tập — đổ bài tập của sách, chuyên đề, bộ đề; thiết kế phần lọc
 * để sau này dễ tìm kiếm và dễ sửa nhất") — dữ liệu cho tab admin.content.index?tab=material_exercises.
 *
 * HAI NGUỒN BÀI TẬP của tài liệu:
 *   1. Bài gắn thẳng vào tài liệu: questions.product_id (nhập ZIP / tạo tay ở trang Tài liệu), thuộc Chương / Phần / Đề nào
 *      thì theo questions.material_id. Nút Sửa mở đúng màn sửa bài của tài liệu (admin.products.exercises.edit).
 *   2. Bộ đề (module bộ đề): câu hỏi của từng đề trong bộ, đúng thứ tự bộ đề đang hiện cho người đọc
 *      (ExamBundleService::items()). Câu này là câu của đề nên nút Sửa mở màn sửa câu hỏi chung.
 *
 * Mỗi dòng = 1 bài trong 1 tài liệu (một câu nằm ở 2 bộ đề thì có 2 dòng — đúng với cách người học nhìn thấy).
 *
 * LỌC (mọi điều kiện nằm ở indexData(), thêm điều kiện mới chỉ sửa một chỗ): Loại tài liệu (chip có số đếm) · Tài liệu ·
 * Chương/Phần/Đề (hiện khi đã chọn tài liệu) · Dạng bài · Trạng thái · Tìm theo tên / mã bài / tên tài liệu (gõ không dấu
 * được). Số dòng chỉ vài trăm – vài nghìn nên lọc & chia trang ngay trên mảng, không cần truy vấn riêng cho từng kiểu lọc.
 */
final class MaterialExerciseIndexService
{
    public const PER_PAGE = 10;

    /** Loại tài liệu → nhãn; thứ tự này cũng là thứ tự sắp xếp danh sách. */
    public const TYPE_LABELS = ['book' => 'Sách', 'topic' => 'Chuyên đề', 'exam' => 'Bộ đề', 'course' => 'Khoá học'];

    public const QUESTION_TYPES = ['coding' => 'Lập trình (OJ)', 'mcq' => 'Trắc nghiệm', 'fill_blank' => 'Điền khuyết', 'composite' => 'Nhiều phần'];

    /** trạng thái → [nhãn, lớp huy hiệu của admin-content-ui] */
    public const STATUSES = [
        'published' => ['Phát hành', 'acx-badge--ok'],
        'draft' => ['Nháp', ''],
        'pending_review' => ['Chờ duyệt', 'acx-badge--warn'],
        'archived' => ['Lưu trữ', ''],
    ];

    private ?Collection $rowsCache = null;

    private int $seq = 0;

    public function __construct(private readonly ExamBundleService $bundles) {}

    /** Số trên tab (đếm nhanh, không dựng cả danh sách). */
    public function count(): int
    {
        $productIds = Product::query()->whereIn('type', array_keys(self::TYPE_LABELS))->select('id');
        $n = Question::query()->whereIn('product_id', $productIds)->count();

        if (ExamBundleService::ready()) {
            foreach (Product::query()->where('type', 'exam')->get() as $product) {
                $n += (int) $this->bundles->items($product)->sum('items_count');
            }
        }

        return $n;
    }

    /**
     * @param  array<string, mixed>  $input  query string của trang (loai, tai_lieu, phan, dang, trang_thai, q)
     */
    public function indexData(array $input, int $page = 1): array
    {
        $f = [
            'loai' => array_key_exists((string) ($input['loai'] ?? ''), self::TYPE_LABELS) ? (string) $input['loai'] : null,
            'tai_lieu' => ctype_digit((string) ($input['tai_lieu'] ?? '')) ? (int) $input['tai_lieu'] : null,
            'phan' => preg_match('/^[am]\d+$/', (string) ($input['phan'] ?? '')) ? (string) $input['phan'] : null,
            'dang' => array_key_exists((string) ($input['dang'] ?? ''), self::QUESTION_TYPES) ? (string) $input['dang'] : null,
            'trang_thai' => array_key_exists((string) ($input['trang_thai'] ?? ''), self::STATUSES) ? (string) $input['trang_thai'] : null,
            'q' => ($q = trim(Str::limit((string) ($input['q'] ?? ''), 100, ''))) !== '' ? $q : null,
        ];

        $all = $this->rows();
        $typeCounts = $all->countBy('productType')->all();

        // Tài liệu: chỉ những tài liệu đang có bài, theo loại đang chọn.
        $byType = $f['loai'] !== null ? $all->where('productType', $f['loai']) : $all;
        $productOptions = $byType->groupBy('productId')->map(fn (Collection $g) => [
            'id' => (int) $g->first()['productId'],
            'title' => $g->first()['productTitle'],
            'typeLabel' => $g->first()['productTypeLabel'],
            'count' => $g->count(),
        ])->sortBy(fn ($o) => Str::lower(Str::ascii($o['title'])))->values()->all();
        if ($f['tai_lieu'] !== null && ! collect($productOptions)->contains('id', $f['tai_lieu'])) {
            $f['tai_lieu'] = null;
        }

        // Chương / Phần / Đề của tài liệu đang chọn.
        $byProduct = $f['tai_lieu'] !== null ? $byType->where('productId', $f['tai_lieu']) : $byType;
        $partWord = $f['tai_lieu'] !== null ? ($byProduct->first()['partWord'] ?? 'Phần') : 'Chương / Phần / Đề';
        $partOptions = $f['tai_lieu'] === null ? [] : $byProduct->groupBy('partKey')->map(fn (Collection $g) => [
            'key' => $g->first()['partKey'],
            'title' => $g->first()['partTitle'] ?? 'Chưa gắn '.Str::lower($partWord),
            'order' => $g->first()['partOrder'],
            'count' => $g->count(),
        ])->sortBy('order')->values()->all();
        if ($f['phan'] !== null && ! collect($partOptions)->contains('key', $f['phan'])) {
            $f['phan'] = null;
        }

        $needle = $f['q'] !== null ? Str::lower(Str::ascii($f['q'])) : null;
        $list = $byProduct
            ->when($f['phan'] !== null, fn ($c) => $c->where('partKey', $f['phan']))
            ->when($f['dang'] !== null, fn ($c) => $c->where('type', $f['dang']))
            ->when($f['trang_thai'] !== null, fn ($c) => $c->where('status', $f['trang_thai']))
            ->when($needle !== null, fn ($c) => $c->filter(fn ($r) => str_contains($r['search'], $needle)))
            ->values();

        $total = $list->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $lastPage);

        return [
            'meFilters' => $f,
            'meRows' => $list->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values()->all(),
            'mePagination' => ['page' => $page, 'perPage' => self::PER_PAGE, 'lastPage' => $lastPage, 'total' => $total],
            'meTotalAll' => $all->count(),
            'meTypeCounts' => $typeCounts,
            'meProductOptions' => $productOptions,
            'mePartOptions' => $partOptions,
            'mePartWord' => $partWord,
            'meTypeLabels' => self::TYPE_LABELS,
            'meQuestionTypes' => self::QUESTION_TYPES,
            'meStatuses' => array_map(fn ($s) => $s[0], self::STATUSES),
        ];
    }

    /** Toàn bộ bài của tài liệu, đã sắp: loại tài liệu → tên tài liệu → chương/phần/đề → thứ tự bài. */
    private function rows(): Collection
    {
        if ($this->rowsCache !== null) {
            return $this->rowsCache;
        }

        $products = Product::query()->whereIn('type', array_keys(self::TYPE_LABELS))->get()->keyBy('id');
        $rows = collect();

        // 1) Bài gắn thẳng vào tài liệu.
        Question::query()
            ->whereIn('product_id', $products->keys()->all() ?: [0])
            ->with('chapter:id,title,order')
            ->orderBy('id')
            ->get()
            ->each(function (Question $q) use ($products, $rows) {
                $p = $products->get($q->product_id);
                if ($p === null) {
                    return;
                }
                $rows->push($this->row(
                    $q, $p,
                    'm'.(int) ($q->material_id ?? 0),
                    $q->chapter?->title,
                    $q->chapter !== null ? (int) $q->chapter->order : PHP_INT_MAX,
                    route('admin.products.exercises.edit', [$p->id, $q->id]),
                    null,
                ));
            });

        // 2) Bộ đề: câu hỏi của từng đề trong bộ.
        if (ExamBundleService::ready()) {
            foreach ($products->filter(fn (Product $p) => $this->bundles->isBundle($p)) as $p) {
                $exams = $this->bundles->items($p)->values();
                if ($exams->isEmpty()) {
                    continue;
                }
                $full = Assessment::query()->whereIn('id', $exams->pluck('id')->all())
                    ->with(['items' => fn ($q) => $q->orderBy('order')->orderBy('id'), 'items.question'])
                    ->get()->keyBy('id');
                foreach ($exams as $ei => $exam) {
                    foreach ($full->get($exam->id)?->items ?? [] as $item) {
                        if ($item->question === null) {
                            continue;
                        }
                        $rows->push($this->row(
                            $item->question, $p,
                            'a'.(int) $exam->id,
                            (string) $exam->title,
                            (int) $ei,
                            route('admin.content.questions.edit', $item->question->id),
                            route('admin.content.show', ['content' => $exam->id, 'kind' => 'assessment']),
                        ));
                    }
                }
            }
        }

        $typeOrder = array_flip(array_keys(self::TYPE_LABELS));

        return $this->rowsCache = $rows->sort(fn ($a, $b) => [
            $typeOrder[$a['productType']] ?? 99, $a['productSort'], $a['productId'], $a['partOrder'], $a['seq'],
        ] <=> [
            $typeOrder[$b['productType']] ?? 99, $b['productSort'], $b['productId'], $b['partOrder'], $b['seq'],
        ])->values();
    }

    private function row(Question $q, Product $p, string $partKey, ?string $partTitle, int $partOrder, string $editHref, ?string $partHref): array
    {
        $type = $q->type instanceof \BackedEnum ? $q->type->value : (string) $q->type;
        $status = $q->status instanceof \BackedEnum ? $q->status->value : (string) $q->status;
        $productType = $p->type instanceof \BackedEnum ? $p->type->value : (string) $p->type;
        [$statusLabel, $statusClass] = self::STATUSES[$status] ?? [$status, ''];
        $metadata = is_array($q->metadata) ? $q->metadata : null;

        return [
            'id' => (int) $q->id,
            'title' => (string) $q->title,
            'code' => (string) ($q->code ?? ''),
            'type' => $type,
            'typeLabel' => self::QUESTION_TYPES[$type] ?? $type,
            'difficulty' => QuestionDifficulty::label(QuestionDifficulty::resolve($metadata, (int) $q->points)),
            'status' => $status,
            'statusLabel' => $statusLabel,
            'statusClass' => $statusClass,
            'productId' => (int) $p->id,
            'productTitle' => (string) $p->title,
            'productSort' => Str::lower(Str::ascii((string) $p->title)),
            'productType' => $productType,
            'productTypeLabel' => self::TYPE_LABELS[$productType] ?? $productType,
            'partWord' => $p->chapterLabel() ?? 'Phần',
            'partKey' => $partKey,
            'partTitle' => $partTitle,
            'partOrder' => $partOrder,
            'editHref' => $editHref,
            'viewHref' => route('admin.content.show', ['content' => $q->id, 'kind' => 'question']),
            'productHref' => route('admin.products.show', $p->id),
            'partHref' => $partHref,
            'search' => Str::lower(Str::ascii(implode(' ', [$q->title, $q->code, $p->title, $partTitle]))),
            'seq' => $this->seq++,
        ];
    }
}
