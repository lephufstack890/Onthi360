<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\Question;
use App\Support\QuestionDifficulty;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * SỬA 27/10 (khách: "thêm tab Bài của tài liệu trong Kho bài tập — đổ bài tập của sách, chuyên đề, bộ đề; thiết kế phần lọc
 * để sau này dễ tìm kiếm và dễ sửa nhất") — dữ liệu cho tab admin.content.index?tab=material_exercises.
 *
 * NGUỒN (SỬA 27/10 lần 2 — khách: "chỉ lấy bài tập từ tài liệu đổ ra thôi"): đúng bài tập của tài liệu — questions.product_id
 * (nhập ZIP / tạo tay ở trang Tài liệu), cùng nguồn với cột "Bài tập" ở màn đọc (Student\MaterialReadService::exercisesFor()).
 * Thuộc Chương / Phần / Đề nào theo questions.material_id. Nút Sửa mở màn sửa bài của tài liệu (admin.products.exercises.edit).
 * Câu hỏi của các đề trong bộ đề KHÔNG đổ vào đây (đó là "Bài của đề").
 *
 * LỌC (mọi điều kiện nằm ở indexData(), thêm điều kiện mới chỉ sửa một chỗ): Loại tài liệu (chip có số đếm) · Tài liệu ·
 * Chương/Phần/Đề (hiện khi đã chọn tài liệu) · Dạng bài · Trạng thái · Tìm theo tên / mã bài / tên tài liệu (gõ không dấu
 * được). Số dòng chỉ vài trăm – vài nghìn nên lọc & chia trang ngay trên mảng, không cần truy vấn riêng cho từng kiểu lọc.
 */
final class MaterialExerciseIndexService
{
    public const PER_PAGE = 10;

    /** Loại tài liệu → nhãn; thứ tự này cũng là thứ tự sắp xếp danh sách. */
    // SỬA 27/10 (lần 3, khách: "có 3 loại sách, chuyên đề, bộ đề — lọc theo 3 loại này") — luôn hiện đủ 3 loại.
    public const TYPE_LABELS = ['book' => 'Sách', 'topic' => 'Chuyên đề', 'exam' => 'Bộ đề'];

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

    /** Số trên tab (đếm nhanh, không dựng cả danh sách). */
    public function count(): int
    {
        $productIds = Product::query()->whereIn('type', array_keys(self::TYPE_LABELS))->select('id');
        return Question::query()->whereIn('product_id', $productIds)->count();
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
        $typeCounts = array_map(fn () => 0, self::TYPE_LABELS);
        foreach ($all->countBy('productType') as $type => $n) {
            $typeCounts[$type] = $n;
        }

        // Tài liệu: MỌI tài liệu thuộc loại đang chọn (kể cả chưa có bài — số trong ngoặc là số bài).
        $byType = $f['loai'] !== null ? $all->where('productType', $f['loai']) : $all;
        $rowCounts = $byType->countBy('productId');
        $typeOrder = array_flip(array_keys(self::TYPE_LABELS));
        $productOptions = Product::query()
            ->whereIn('type', $f['loai'] !== null ? [$f['loai']] : array_keys(self::TYPE_LABELS))
            ->get(['id', 'title', 'type'])
            ->map(function (Product $p) use ($rowCounts) {
                $type = $p->type instanceof \BackedEnum ? $p->type->value : (string) $p->type;

                return ['id' => (int) $p->id, 'title' => (string) $p->title, 'type' => $type, 'typeLabel' => self::TYPE_LABELS[$type] ?? $type, 'count' => (int) ($rowCounts[$p->id] ?? 0)];
            })
            ->sortBy(fn ($o) => [$typeOrder[$o['type']] ?? 9, Str::lower(Str::ascii($o['title']))])
            ->values()->all();
        if ($f['tai_lieu'] !== null && ! collect($productOptions)->contains('id', $f['tai_lieu'])) {
            $f['tai_lieu'] = null;
        }

        // Chương / Phần / Đề của tài liệu đang chọn.
        $byProduct = $f['tai_lieu'] !== null ? $byType->where('productId', $f['tai_lieu']) : $byType;
        $partWord = 'Chương / Phần / Đề';
        $partOptions = [];
        if ($f['tai_lieu'] !== null) {
            // Mọi chương / phần / đề của tài liệu (kể cả chưa có bài), đúng thứ tự ở trang quản lý tài liệu.
            $product = Product::query()->find($f['tai_lieu']);
            $partWord = $product?->chapterLabel() ?? 'Phần';
            $partCounts = $byProduct->countBy('partKey');
            $partOptions = \App\Models\Material::query()
                ->where('product_id', $f['tai_lieu'])->where('type', 'chapter')->whereNull('parent_id')
                ->orderBy('order')->orderBy('id')->get(['id', 'title'])
                ->map(fn ($m) => ['key' => 'm'.$m->id, 'title' => (string) $m->title, 'count' => (int) ($partCounts['m'.$m->id] ?? 0)])
                ->all();
            if (($partCounts['m0'] ?? 0) > 0) {
                $partOptions[] = ['key' => 'm0', 'title' => 'Chưa gắn '.Str::lower($partWord), 'count' => (int) $partCounts['m0']];
            }
        }
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

    /** Dữ liệu rỗng kèm thông báo — dùng khi dựng danh sách lỗi (ContentController bắt lỗi, trang vẫn mở, lỗi ghi log). */
    public function emptyData(string $message): array
    {
        return [
            'meFilters' => ['loai' => null, 'tai_lieu' => null, 'phan' => null, 'dang' => null, 'trang_thai' => null, 'q' => null],
            'meRows' => [],
            'mePagination' => ['page' => 1, 'perPage' => self::PER_PAGE, 'lastPage' => 1, 'total' => 0],
            'meTotalAll' => 0,
            'meTypeCounts' => [],
            'meProductOptions' => [],
            'mePartOptions' => [],
            'mePartWord' => 'Chương / Phần / Đề',
            'meTypeLabels' => self::TYPE_LABELS,
            'meQuestionTypes' => self::QUESTION_TYPES,
            'meStatuses' => array_map(fn ($s) => $s[0], self::STATUSES),
            'meError' => $message,
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

        // Bài tập gắn vào tài liệu.
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
