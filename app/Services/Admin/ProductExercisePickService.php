<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Models\Material;
use App\Models\Product;
use App\Models\Question;
use App\Models\User;
use App\Models\ProductExerciseLink;
use App\Support\ProductExerciseLinks;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 27/10 (khách: "thêm option chọn up từ bài có sẵn — loại bài của tài liệu; 1 bài dùng ở chương của tài liệu này nhưng
 * cũng dùng ở chương của tài liệu khác. Cho chọn sách, chọn chương để lọc bài cho dễ. Bài nào có rồi thì đừng hiển thị
 * trong option để khỏi chọn trùng").
 *
 * SỬA 27/10 lần 2 (khách: "chỉ dùng ké, không lưu thêm — thêm nhiều nặng; dùng chung") — KHÔNG sao chép nữa. "Thêm" = tạo
 * một dòng product_exercise_links (tài liệu này dùng ké bài gốc, đặt ở chương chọn). Không chép bài, không chép tệp; sửa bài
 * gốc là mọi nơi thấy ngay; xoá bài gốc thì tự gỡ khỏi mọi nơi dùng ké. Xem App\Support\ProductExerciseLinks.
 *
 * "BÀI ĐÃ CÓ" trong tài liệu đích (không hiện để chọn, máy chủ chặn lại lần nữa khi bấm thêm): bài của chính tài liệu này,
 * bài nó đang dùng ké, và (với các bản sao tạo bằng cách cũ — metadata.copied_from) bài gốc của bản sao đó.
 */
final class ProductExercisePickService
{
    /** Loại tài liệu được lấy bài (cùng 3 loại của tab "Bài của tài liệu"). */
    public const TYPE_LABELS = MaterialExerciseIndexService::TYPE_LABELS;

    /** Tối đa số bài hiện trong danh sách chọn / thêm trong 1 lần. */
    public const MAX_ITEMS = 300;

    /**
     * Dữ liệu cho khối chọn: danh sách tài liệu nguồn (kèm số bài còn chọn được), chương của tài liệu đang lọc, và các bài.
     *
     * @param  array<string, mixed>  $input  loai, tai_lieu, chuong ('none' = chưa gắn chương), q
     */
    public function sources(Product $target, array $input): array
    {
        $loai = array_key_exists((string) ($input['loai'] ?? ''), self::TYPE_LABELS) ? (string) $input['loai'] : null;
        $productId = ctype_digit((string) ($input['tai_lieu'] ?? '')) ? (int) $input['tai_lieu'] : null;
        $chapter = (string) ($input['chuong'] ?? '');
        $kw = self::searchKey((string) ($input['q'] ?? ''));

        $taken = $this->takenOrigins($target);

        // Mọi bài đã phát hành của các tài liệu khác (chỉ lấy cột cần), bỏ bài đã có ở tài liệu đích.
        $all = Question::query()
            ->whereNotNull('product_id')
            ->where('product_id', '!=', $target->id)
            ->whereIn('product_id', Product::query()->whereIn('type', array_keys(self::TYPE_LABELS))->select('id'))
            ->where('status', ContentStatus::Published->value)
            ->get(['id', 'code', 'title', 'type', 'points', 'product_id', 'material_id', 'metadata', 'grading_config'])
            ->reject(fn (Question $q) => isset($taken[$q->id]) || isset($taken[self::originId($q)]))
            ->values();

        $products = Product::query()->whereIn('id', $all->pluck('product_id')->unique()->values()->all() ?: [0])
            ->get(['id', 'title', 'type'])
            ->map(fn (Product $p) => [
                'id' => (int) $p->id,
                'title' => (string) $p->title,
                'type' => self::typeValue($p),
                'typeLabel' => self::TYPE_LABELS[self::typeValue($p)] ?? '',
                'count' => $all->where('product_id', $p->id)->count(),
            ])
            ->filter(fn ($p) => $loai === null || $p['type'] === $loai)
            ->sortBy(fn ($p) => array_search($p['type'], array_keys(self::TYPE_LABELS), true).'|'.Str::lower($p['title']))
            ->values();

        if ($productId !== null && ! $products->contains('id', $productId)) {
            $productId = null; // tài liệu không còn bài nào chọn được / không thuộc loại đang lọc
        }

        $chapters = [];
        if ($productId !== null) {
            $inProduct = $all->where('product_id', $productId);
            $chapters = Material::query()->chapters()->where('product_id', $productId)->orderBy('order')->get(['id', 'title'])
                ->map(fn (Material $m) => ['id' => (int) $m->id, 'title' => (string) $m->title, 'count' => $inProduct->where('material_id', $m->id)->count()])
                ->filter(fn ($c) => $c['count'] > 0)->values()->all();
            $noChapter = $inProduct->whereNull('material_id')->count();
            if ($noChapter > 0) {
                $chapters[] = ['id' => 'none', 'title' => '— Chưa gắn chương —', 'count' => $noChapter];
            }
        }

        // Chưa chọn tài liệu thì chỉ hiện bài khi có gõ tìm (tránh đổ cả nghìn bài).
        $items = collect();
        if ($productId !== null || mb_strlen($kw) >= 2) {
            $allowedProducts = $products->pluck('id')->all();
            $items = $all
                ->filter(fn (Question $q) => in_array((int) $q->product_id, $allowedProducts, true))
                ->filter(fn (Question $q) => $productId === null || (int) $q->product_id === $productId)
                ->filter(fn (Question $q) => $productId === null || $chapter === '' || ($chapter === 'none' ? $q->material_id === null : (string) $q->material_id === $chapter))
                ->filter(fn (Question $q) => $kw === '' || str_contains(self::searchKey($q->code.' '.$q->title), $kw));
        }

        $total = $items->count();
        $items = $this->sorted($items)->take(self::MAX_ITEMS);
        $chapterTitles = Material::query()->whereIn('id', $items->pluck('material_id')->filter()->unique()->values()->all() ?: [0])->pluck('title', 'id');
        $productTitles = $products->pluck('title', 'id');

        return [
            'products' => $products->all(),
            'chapters' => $chapters,
            'selected' => ['loai' => $loai, 'tai_lieu' => $productId, 'chuong' => $productId !== null ? $chapter : ''],
            'total' => $total,
            'items' => $items->map(fn (Question $q) => [
                'id' => (int) $q->id,
                'code' => (string) $q->code,
                'title' => (string) $q->title,
                'typeLabel' => self::typeLabel($q),
                'summary' => $q->exerciseSummaryLabel(),
                'points' => (int) $q->points,
                'product' => (string) ($productTitles[$q->product_id] ?? ''),
                'chapter' => $q->material_id !== null ? (string) ($chapterTitles[$q->material_id] ?? '') : '',
                // Cùng bài gốc → cùng root: giao diện không cho tick 2 bài cùng gốc trong 1 lượt.
                'root' => self::originId($q),
                'copyOf' => isset($q->metadata['copied_from']['code']) ? (string) $q->metadata['copied_from']['code'] : null,
            ])->values()->all(),
        ];
    }

    /**
     * Thêm các bài đã chọn vào tài liệu đích theo kiểu DÙNG KÉ (gắn chương $chapterId). Bài lỗi / đã có thì bỏ qua và báo lại,
     * các bài khác vẫn thêm.
     *
     * @param  array<int, int>  $ids
     * @return array{added: array, skipped: array}
     */
    public function link(Product $target, User $admin, array $ids, ?int $chapterId): array
    {
        if (! ProductExerciseLinks::supported()) {
            throw ValidationException::withMessages(['ids' => 'Máy chủ chưa cập nhật CSDL cho tính năng dùng chung bài — chạy "php artisan migrate" rồi thử lại.']);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            throw ValidationException::withMessages(['ids' => 'Chưa chọn bài nào.']);
        }
        if (count($ids) > self::MAX_ITEMS) {
            throw ValidationException::withMessages(['ids' => 'Mỗi lần thêm tối đa '.self::MAX_ITEMS.' bài.']);
        }

        $chapterId = $chapterId !== null ? $target->chapters()->whereKey($chapterId)->value('id') : null;
        $taken = $this->takenOrigins($target);
        $sources = Question::query()->whereIn('id', $ids)->with(['product:id,title,type'])->get()->keyBy('id');

        $added = [];
        $skipped = [];
        $batchRoots = []; // gốc → mã bài vừa thêm trong lượt này
        foreach ($ids as $id) {
            /** @var Question|null $src */
            $src = $sources->get($id);
            $reason = match (true) {
                $src === null => 'Bài không còn tồn tại.',
                $src->product_id === null || ! array_key_exists(self::typeValue($src->product), self::TYPE_LABELS) => 'Không phải bài tập của tài liệu.',
                (int) $src->product_id === (int) $target->id => 'Bài đã nằm trong tài liệu này.',
                $src->status !== ContentStatus::Published => 'Bài gốc chưa phát hành.',
                isset($batchRoots[self::originId($src)]) => 'Trùng với bài '.$batchRoots[self::originId($src)].' vừa thêm ở trên (cùng một bài gốc).',
                isset($taken[$src->id]) || isset($taken[self::originId($src)]) => 'Tài liệu này đã có bài này rồi.',
                default => null,
            };
            if ($reason !== null) {
                $skipped[] = ['code' => $src?->code ?? '#'.$id, 'title' => $src?->title ?? '', 'reason' => $reason];

                continue;
            }

            ProductExerciseLink::query()->firstOrCreate(
                ['product_id' => $target->id, 'question_id' => $src->id],
                ['material_id' => $chapterId, 'created_by' => $admin->id],
            );
            $taken[(int) $src->id] = true;
            $taken[self::originId($src)] = true;
            $batchRoots[self::originId($src)] = (string) $src->code;
            $added[] = ['code' => (string) $src->code, 'title' => (string) $src->title, 'from' => (string) ($src->product?->title ?? '')];
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * SỬA 27/10 — gỡ nhiều bài dùng chung khỏi tài liệu (chỉ xoá dòng liên kết của CHÍNH tài liệu này; bài gốc, tệp và
     * các tài liệu khác không đụng tới). Trả số bài đã gỡ.
     *
     * @param  array<int, int|string>  $ids
     */
    public function unlinkMany(Product $target, array $ids): int
    {
        if (! ProductExerciseLinks::supported() || $ids === []) {
            return 0;
        }

        return ProductExerciseLink::query()->where('product_id', $target->id)->whereIn('question_id', array_map('intval', $ids))->delete();
    }

    /** Gỡ một bài dùng ké khỏi tài liệu (bài gốc giữ nguyên). */
    public function unlink(Product $target, Question $question): bool
    {
        if (! ProductExerciseLinks::supported()) {
            return false;
        }

        return ProductExerciseLink::query()->where('product_id', $target->id)->where('question_id', $question->id)->delete() > 0;
    }

    /** id bài gốc + id mọi bài đang nằm trong tài liệu đích (tính cả nháp và bài đang dùng ké) → để ẩn / chặn chọn trùng. */
    private function takenOrigins(Product $target): array
    {
        $taken = [];
        Question::query()->where('product_id', $target->id)->get(['id', 'metadata'])
            ->each(function (Question $q) use (&$taken) {
                $taken[(int) $q->id] = true;
                $taken[self::originId($q)] = true;
            });
        foreach (ProductExerciseLinks::linkedQuestionIds((int) $target->id) as $id) {
            $taken[$id] = true;
        }

        return $taken;
    }

    private static function originId(Question $q): int
    {
        $root = $q->metadata['copied_from']['id'] ?? null;

        return is_numeric($root) ? (int) $root : (int) $q->id;
    }

    /** Thứ tự: tài liệu → chương (theo thứ tự chương) → mã bài tự nhiên (T1B002 trước T1B010). */
    private function sorted(Collection $items): Collection
    {
        $order = Material::query()->whereIn('id', $items->pluck('material_id')->filter()->unique()->values()->all() ?: [0])->pluck('order', 'id');

        return $items->sort(function (Question $a, Question $b) use ($order) {
            return [(int) $a->product_id, $a->material_id === null ? PHP_INT_MAX : (int) ($order[$a->material_id] ?? 0)]
                <=> [(int) $b->product_id, $b->material_id === null ? PHP_INT_MAX : (int) ($order[$b->material_id] ?? 0)]
                ?: (strnatcasecmp((string) $a->code, (string) $b->code) ?: $a->id <=> $b->id);
        })->values();
    }

    private static function typeLabel(Question $q): string
    {
        $type = $q->type instanceof \BackedEnum ? $q->type->value : (string) $q->type;

        return match (true) {
            ($q->metadata['manual_variant'] ?? null) === 'true_false',
            ($q->metadata['source_package']['content_type'] ?? null) === 'true_false' => 'Đúng / Sai',
            $type === 'mcq' => 'Trắc nghiệm',
            $type === 'fill_blank' => 'Điền từ',
            $type === 'coding' => 'Lập trình',
            $type === 'composite' => 'Nhiều phần',
            default => $type,
        };
    }

    private static function typeValue(?Product $p): string
    {
        if ($p === null) {
            return '';
        }

        return $p->type instanceof \BackedEnum ? (string) $p->type->value : (string) $p->type;
    }

    /** Chữ thường, bỏ dấu (đ → d) — gõ "nhap xuat" vẫn ra "Nhập xuất". */
    private static function searchKey(string $text): string
    {
        $t = mb_strtolower(trim($text));

        return (string) preg_replace('/\s+/', ' ', Str::ascii(str_replace('đ', 'd', $t)));
    }
}
