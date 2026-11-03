<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * SỬA 3/11 (khách: "đề có nhiều mã đề: bấm Bắt đầu làm bài thì hiện popup chọn mã đề rồi vào làm; ngoài trang phải
 * nhìn ra được đề có mã đề") — NHÓM MÃ ĐỀ. Toàn bộ logic nằm ở đây; chỗ khác chỉ gọi vào.
 *
 * Mỗi mã đề là MỘT ĐỀ RIÊNG như hiện nay (màn làm bài, chấm, nhật ký, kết quả… giữ nguyên). Nhóm = ĐỀ GỐC
 * (variant_of_id = null) + các đề có variant_of_id = id đề gốc. Ngoài trang Luyện tập chỉ hiện ĐỀ GỐC (1 thẻ / nhóm).
 * Đề không thuộc nhóm nào → mọi thứ như cũ (bấm Bắt đầu làm bài là vào làm luôn).
 */
class ExamVariantService
{
    public static function ready(): bool
    {
        return Assessment::supportsVariants();
    }

    /** id đề gốc của nhóm chứa $exam (đề lẻ thì là chính nó). */
    public static function rootId(Assessment $exam): int
    {
        return (int) (self::ready() && $exam->variant_of_id ? $exam->variant_of_id : $exam->id);
    }

    /**
     * Các đề trong nhóm (kể cả đề gốc), xếp theo tên mã (101 < 102 < 110), đề gốc chưa đặt mã đứng đầu.
     * $publicOnly: chỉ đề Luyện tập đã phát hành (đúng những đề học sinh làm được).
     *
     * @return Collection<int, Assessment>
     */
    public static function members(Assessment $exam, bool $publicOnly = false): Collection
    {
        if (! self::ready()) {
            return collect([$exam]);
        }
        $rootId = self::rootId($exam);

        $list = Assessment::query()
            ->where(fn ($q) => $q->where('id', $rootId)->orWhere('variant_of_id', $rootId))
            ->when($publicOnly, fn ($q) => $q->where('type', 'practice')->where('status', 'published'))
            ->withCount(['items', 'answerKeys'])
            ->get();

        return $list->sort(function (Assessment $a, Assessment $b) use ($rootId) {
            $ca = (string) ($a->variant_code ?? '');
            $cb = (string) ($b->variant_code ?? '');
            if ($ca === '' || $cb === '') {
                // chưa đặt mã: đề gốc trước, còn lại theo id
                $ka = $ca === '' ? ($a->id === $rootId ? 0 : 2) : 1;
                $kb = $cb === '' ? ($b->id === $rootId ? 0 : 2) : 1;

                return $ka <=> $kb ?: $a->id <=> $b->id;
            }

            return strnatcasecmp($ca, $cb) ?: $a->id <=> $b->id;
        })->values();
    }

    /** Tên mã hiển thị. */
    public static function label(Assessment $exam, int $index = 0): string
    {
        $code = trim((string) ($exam->variant_code ?? ''));

        return $code !== '' ? $code : 'Mã '.($index + 1);
    }

    /**
     * Dữ liệu cho trang chi tiết đề (công khai): [] khi đề không có từ 2 mã trở lên (trang hiện như cũ).
     *
     * @return list<array<string, mixed>>
     */
    public static function forViewer(Assessment $exam, ?User $viewer): array
    {
        if (! self::ready()) {
            return [];
        }
        $members = self::members($exam, publicOnly: true);
        if ($members->count() < 2) {
            return [];
        }

        $mine = [];
        if ($viewer !== null) {
            Attempt::query()
                ->where('user_id', $viewer->id)
                ->whereIn('assessment_id', $members->pluck('id')->all())
                ->whereNotNull('submitted_at')
                ->orderByDesc('submitted_at')
                ->get(['assessment_id', 'total_score'])
                ->each(function ($at) use (&$mine) {
                    $id = (int) $at->assessment_id;
                    $mine[$id] ??= ['count' => 0, 'last' => $at->total_score !== null ? (float) $at->total_score : null];
                    $mine[$id]['count']++;
                });
        }

        return $members->values()->map(fn (Assessment $m, int $i) => [
            'id' => (int) $m->id,
            'label' => self::label($m, $i),
            'current' => (int) $m->id === (int) $exam->id,
            'itemsCount' => (int) ($m->items_count > 0 ? $m->items_count : ($m->answer_keys_count ?? 0)),
            'duration' => $m->duration_minutes ? (int) $m->duration_minutes : null,
            'totalPoints' => (float) $m->total_points,
            'detailHref' => route('practice.exam.show', $m->id),
            'takeHref' => $viewer !== null ? route('student.assessment.take', $m->id) : route('login'),
            'myAttempts' => (int) ($mine[$m->id]['count'] ?? 0),
            'myLast' => $mine[$m->id]['last'] ?? null,
        ])->all();
    }

    /**
     * Số mã đề (đã phát hành) của từng đề gốc — cho huy hiệu "N mã đề" trên thẻ đề. Đề không có nhóm thì không có khoá.
     *
     * @param  list<int>  $rootIds
     * @return array<int, int>
     */
    public static function publicCounts(array $rootIds): array
    {
        if (! self::ready() || $rootIds === []) {
            return [];
        }

        return Assessment::query()
            ->whereIn('variant_of_id', $rootIds)
            ->where('type', 'practice')
            ->where('status', 'published')
            ->groupBy('variant_of_id')
            ->selectRaw('variant_of_id, COUNT(*) as n')
            ->pluck('n', 'variant_of_id')
            ->mapWithKeys(fn ($n, $id) => [(int) $id => (int) $n + 1])
            ->all();
    }

    // ───────────────────────── Admin: gắn / gỡ / đặt tên mã ─────────────────────────

    /** Đề còn tự do để gắn làm mã đề cho $exam (Luyện tập, không phải Bài phổ thông, chưa thuộc nhóm nào). */
    public static function candidates(Assessment $exam): Collection
    {
        if (! self::ready()) {
            return collect();
        }
        $rootId = self::rootId($exam);
        $groupRoots = Assessment::query()->whereNotNull('variant_of_id')->distinct()->pluck('variant_of_id')->all();

        return Assessment::query()
            ->where('type', 'practice')
            ->whereNull('variant_of_id')
            ->where('id', '!=', $rootId)
            ->whereNotIn('id', $groupRoots)
            ->when(Assessment::supportsPracticeKind(), fn ($q) => $q->where(fn ($w) => $w->whereNull('practice_kind')->orWhere('practice_kind', '!=', Assessment::PRACTICE_KIND_GENERAL)))
            ->latest()
            ->limit(500)
            ->get(['id', 'title', 'exam_code', 'status']);
    }

    /** Gắn $child vào nhóm của $exam. @return array{0: bool, 1: string} */
    public static function attach(Assessment $exam, Assessment $child, ?string $code): array
    {
        if (! self::ready()) {
            return [false, 'Máy chủ chưa chạy migration mã đề (php artisan migrate).'];
        }
        $rootId = self::rootId($exam);
        if ((int) $child->id === $rootId || (int) $child->variant_of_id === $rootId) {
            return [false, 'Đề này đã nằm trong nhóm mã đề rồi.'];
        }
        if ($child->variant_of_id) {
            return [false, 'Đề "'.$child->title.'" đang thuộc nhóm mã đề khác — gỡ ra trước rồi hãy gắn.'];
        }
        if (Assessment::query()->where('variant_of_id', $child->id)->exists()) {
            return [false, 'Đề "'.$child->title.'" đang là đề gốc của một nhóm mã đề khác.'];
        }
        if ($child->isGeneralSet() || ($child->type?->value ?? $child->type) !== 'practice') {
            return [false, 'Chỉ gắn được đề Luyện tập (không phải Bài phổ thông).'];
        }

        if (($dup = self::duplicateCode($exam, $code, null)) !== null) {
            return [false, $dup];
        }

        $child->forceFill(['variant_of_id' => $rootId, 'variant_code' => self::cleanCode($code)])->save();
        self::forgetListCache();

        return [true, 'Đã gắn "'.$child->title.'" làm một mã đề của nhóm.'];
    }

    /** Đặt / đổi tên mã của một đề. Trả về câu báo lỗi (trùng tên mã trong nhóm) hoặc null khi đã lưu. */
    public static function setCode(Assessment $exam, ?string $code): ?string
    {
        if (! self::ready()) {
            return null;
        }
        if (($dup = self::duplicateCode($exam, $code, (int) $exam->id)) !== null) {
            return $dup;
        }
        $exam->forceFill(['variant_code' => self::cleanCode($code)])->save();
        self::forgetListCache();

        return null;
    }

    /** SỬA 3/11 — 2 mã trong cùng nhóm không được trùng tên (vd cả hai đều "0502" thì học sinh không phân biệt được). */
    private static function duplicateCode(Assessment $exam, ?string $code, ?int $exceptId): ?string
    {
        $clean = self::cleanCode($code);
        if ($clean === null) {
            return null;
        }
        $taken = self::members($exam)->first(fn (Assessment $m) => (int) $m->id !== (int) $exceptId
            && mb_strtolower(trim((string) $m->variant_code)) === mb_strtolower($clean));

        return $taken ? 'Tên mã "'.$clean.'" đã dùng cho đề "'.$taken->title.'" trong cùng nhóm — mỗi mã phải có tên khác nhau.' : null;
    }

    /** Gỡ $exam khỏi nhóm — đề trở lại thành đề lẻ bình thường, KHÔNG mất lượt làm nào. @return array{0: bool, 1: string} */
    public static function detach(Assessment $exam): array
    {
        if (! self::ready()) {
            return [false, 'Máy chủ chưa chạy migration mã đề.'];
        }

        if ($exam->variant_of_id) {
            $exam->forceFill(['variant_of_id' => null])->save();
        } else {
            // Gỡ ĐỀ GỐC: mã kế tiếp lên làm đề gốc mới, các mã còn lại trỏ sang đó.
            $others = self::members($exam)->reject(fn ($m) => (int) $m->id === (int) $exam->id)->values();
            if ($others->isEmpty()) {
                return [false, 'Đề này không thuộc nhóm mã đề nào.'];
            }
            $newRoot = $others->first();
            $newRoot->forceFill(['variant_of_id' => null])->save();
            Assessment::query()->whereIn('id', $others->slice(1)->pluck('id')->all())->update(['variant_of_id' => $newRoot->id]);
        }
        self::forgetListCache();

        return [true, 'Đã gỡ "'.$exam->title.'" khỏi nhóm mã đề — đề hiện lại như một đề lẻ.'];
    }

    private static function cleanCode(?string $code): ?string
    {
        $code = trim((string) $code);

        return $code === '' ? null : mb_substr($code, 0, 30);
    }

    private static function forgetListCache(): void
    {
        \App\Services\Public\PracticeService::forgetExamListCache();
    }
}
