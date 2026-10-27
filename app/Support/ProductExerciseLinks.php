<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Models\Product;
use App\Models\ProductExerciseLink;
use App\Models\Question;
use App\Models\User;
use App\Services\AccessGateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 27/10 (khách: "dùng bài có sẵn mà lưu thêm thì nặng — không lưu thêm, DÙNG CHUNG") — bài tập DÙNG KÉ.
 *
 * Bài gốc vẫn thuộc đúng 1 tài liệu (questions.product_id = A, material_id = chương bên A). Tài liệu B "dùng ké" bài đó qua
 * bảng product_exercise_links (product_id = B, question_id, material_id = chương bên B) — KHÔNG chép bài, KHÔNG chép tệp.
 * Mọi chỗ liệt kê / mở bài tập của một tài liệu gọi vào lớp này để thấy thêm bài dùng ké:
 *   · withLinked()      — gộp bài dùng ké vào danh sách bài của tài liệu (màn đọc, Tài liệu của tôi, lớp học, admin);
 *   · siblingIds()      — thứ tự Bài trước / Bài tiếp trong tài liệu (gồm cả bài dùng ké);
 *   · canAccess()       — học sinh có quyền tài liệu A HOẶC bất kỳ tài liệu nào đang dùng ké bài đó thì được mở bài;
 *   · contextProduct()  — mở bài từ tài liệu nào (để Bài trước / Bài tiếp đi đúng danh sách của tài liệu đó).
 *
 * Bài dùng ké lấy về được gắn chương bên tài liệu đang xem — CHỈ TRONG BỘ NHỚ (setRawAttributes(..., sync: true) nên model
 * không "bẩn", lỡ có ai gọi save() cũng không ghi gì xuống CSDL). Chưa chạy migration 001310 thì mọi hàm trả như chưa có
 * bài dùng ké nào (hệ thống chạy y như cũ).
 */
final class ProductExerciseLinks
{
    public static function supported(): bool
    {
        static $has = null;

        return $has ??= Schema::hasTable('product_exercise_links');
    }

    /** @return array<int, ?int> id bài dùng ké → id chương bên tài liệu này (null = chưa gắn chương) */
    public static function chapterMap(int $productId): array
    {
        if (! self::supported()) {
            return [];
        }

        return ProductExerciseLink::query()->where('product_id', $productId)->pluck('material_id', 'question_id')
            ->map(fn ($m) => $m !== null ? (int) $m : null)->all();
    }

    /** @return list<int> */
    public static function linkedQuestionIds(int $productId): array
    {
        return array_map('intval', array_keys(self::chapterMap($productId)));
    }

    /**
     * Bài dùng ké của tài liệu (mặc định chỉ bài ĐÃ PHÁT HÀNH), đã gắn chương bên tài liệu này.
     *
     * @param  array<int|string, mixed>  $with
     * @param  array<int, string>  $columns
     */
    public static function linkedQuestions(int $productId, array $with = [], array $columns = ['*'], bool $publishedOnly = true): Collection
    {
        $map = self::chapterMap($productId);
        if ($map === []) {
            return collect();
        }

        $query = Question::query()->whereIn('id', array_keys($map))
            ->whereNotNull('product_id')->where('product_id', '!=', $productId)
            ->with($with);
        if ($publishedOnly) {
            $query->where('status', ContentStatus::Published->value);
        }
        if ($columns !== ['*'] && ! in_array('material_id', $columns, true)) {
            $columns[] = 'material_id';
        }
        if ($columns !== ['*'] && ! in_array('product_id', $columns, true)) {
            $columns[] = 'product_id';
        }

        return $query->get($columns)->map(fn (Question $q) => self::asLinked($q, $productId, $map[(int) $q->id] ?? null))->values();
    }

    /** Gộp bài dùng ké vào danh sách bài của tài liệu (bài đã có trong $own thì bỏ qua). */
    public static function withLinked(int $productId, Collection $own, array $with = [], array $columns = ['*'], bool $publishedOnly = true): Collection
    {
        $linked = self::linkedQuestions($productId, $with, $columns, $publishedOnly);
        if ($linked->isEmpty()) {
            return $own;
        }
        $ownIds = $own->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $own->concat($linked->reject(fn (Question $q) => in_array((int) $q->id, $ownIds, true)))->values();
    }

    /** Bài này đang được hiển thị dưới dạng dùng ké (lấy qua linkedQuestions()) hay không. */
    public static function isLinked(Question $q): bool
    {
        return $q->getAttribute('linked_into_product_id') !== null;
    }

    /** @return list<int> các tài liệu đang dùng ké bài này (không gồm tài liệu gốc) */
    public static function linkingProductIds(int $questionId): array
    {
        if (! self::supported()) {
            return [];
        }

        return ProductExerciseLink::query()->where('question_id', $questionId)->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Bài này có thuộc tài liệu $productId không — là bài gốc của nó, hoặc nó đang dùng ké. */
    public static function belongsTo(Question $q, int $productId): bool
    {
        return (int) $q->product_id === $productId || in_array($productId, self::linkingProductIds((int) $q->id), true);
    }

    /** Học sinh / giáo viên có quyền với tài liệu gốc HOẶC một tài liệu đang dùng ké bài này. */
    public static function canAccess(?User $user, Question $q, AccessGateService $gate): bool
    {
        return $user !== null && self::contextProduct($user, $q, $gate) !== null;
    }

    /**
     * Tài liệu mà người dùng đang "đứng" khi mở bài: ưu tiên $preferredProductId (màn đọc gửi kèm), rồi tài liệu gốc, rồi
     * tài liệu dùng ké đầu tiên mà người này có quyền. null = không có quyền với tài liệu nào chứa bài.
     */
    public static function contextProduct(User $user, Question $q, AccessGateService $gate, ?int $preferredProductId = null): ?Product
    {
        if ($q->product_id === null) {
            return null;
        }

        $ids = array_values(array_unique(array_merge([(int) $q->product_id], self::linkingProductIds((int) $q->id))));
        if ($preferredProductId !== null && in_array($preferredProductId, $ids, true)) {
            $ids = array_values(array_unique(array_merge([$preferredProductId], $ids)));
        }

        $products = Product::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $product = $products->get($id);
            if ($product !== null && $gate->canAccessProduct($user, $product)->allowed) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Thứ tự Bài trước / Bài tiếp của một tài liệu: bài đã phát hành của chính nó + bài dùng ké, theo chương rồi mã bài
     * (QuestionOrder::sortMaterialExercises — cùng thứ tự cột Bài tập).
     *
     * @return list<int>
     */
    public static function siblingIds(int $productId): array
    {
        $own = Question::query()->where('product_id', $productId)->where('status', ContentStatus::Published->value)
            ->get(['id', 'code', 'material_id', 'product_id']);

        return QuestionOrder::sortMaterialExercises(self::withLinked($productId, $own, [], ['id', 'code', 'material_id', 'product_id']))
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    private static function asLinked(Question $q, int $productId, ?int $chapterId): Question
    {
        $attributes = $q->getAttributes();
        $attributes['owner_material_id'] = $attributes['material_id'] ?? null;
        $attributes['material_id'] = $chapterId;
        $attributes['linked_into_product_id'] = $productId;
        $q->setRawAttributes($attributes, true); // sync: không "bẩn" → không bao giờ bị ghi xuống CSDL
        $q->unsetRelation('chapter'); // chương bên tài liệu gốc không đúng với tài liệu này

        return $q;
    }
}
