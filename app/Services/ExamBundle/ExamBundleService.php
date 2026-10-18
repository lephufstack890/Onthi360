<?php

namespace App\Services\ExamBundle;

use App\Enums\ContentStatus;
use App\Enums\ProductType;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Product;
use App\Models\ProductAssessment;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Services\AccessGateService;
use App\Support\ExamBundleFilters;
use App\Support\ProvinceCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * SỬA 18/10 — MODULE BỘ ĐỀ. Mọi logic bộ đề nằm ở ĐÂY, các nơi khác chỉ gọi vào:
 *
 *   Bộ đề = tài liệu loại "Bộ đề" (products.type = exam). Bộ đề KHÔNG chứa bản sao đề — nó TRỎ tới các đề luyện tập
 *   đã phát hành (assessments.type = practice). Danh sách đề của 1 bộ được TÍNH mỗi lần xem (items()):
 *
 *     (đề khớp bộ lọc products.bundle_filters)      — trừ khi bộ đề đang "Khoá danh sách"
 *   + (đề admin ghim tay: product_assessments.source = manual)
 *   − (đề admin đã bấm "Bỏ khỏi bộ đề": excluded = 1)
 *     rồi xếp theo sort_order tay (nếu có), còn lại: năm học mới trước → tỉnh A–Z → đề mới trước.
 *
 *   Vì tính động nên: đề mới phát hành khớp bộ lọc TỰ VÀO bộ; đề bị gỡ phát hành / xoá TỰ RA; sửa đề ở Kho là bộ đề thấy
 *   ngay — không cần đồng bộ, không cần cron. Khoá danh sách = chụp lại danh sách hiện tại vào bảng rồi ngừng áp bộ lọc.
 *
 *   Quyền: bộ đề PHẢI MUA (canUseBundle — quyền cá nhân còn hạn / lớp được gắn cả tài liệu / admin). Đề CÔNG KHAI trong
 *   bộ vẫn làm miễn phí ở Luyện tập như cũ; đề "Chỉ trong bộ đề" (assessments.bundle_only) bị ẩn khỏi Luyện tập và chỉ
 *   người có quyền một bộ đề chứa nó mới làm được (assertCanTake, gọi từ Student\AssessmentService::buildTakeData).
 *
 * Nơi gọi vào module (khi bảo trì, sửa đúng các chỗ này):
 *   - Admin\ExamBundleController + views admin/products/partials/exam-bundle, admin/content/partials/assessment-bundle-box
 *   - Public\ExamBundleController + view public/bundles/show, partials/bundle-cta (trang chi tiết tài liệu)
 *   - Public\PracticeService::examBaseRows()       : ẩn đề bundle_only khỏi trang Luyện tập (scopeHiddenBundleOnly)
 *   - Student\AssessmentService::buildTakeData()   : chặn làm đề bundle_only khi chưa mua
 *   - ExamZipImportService::importPracticeForAdmin : exam.json "visibility": "bundle_only", "bundles": [...]
 * Chưa chạy migration (thiếu bảng/cột) thì ready() = false và mọi hàm trả "không có gì" — trang cũ chạy y như trước.
 */
class ExamBundleService
{
    private ?Collection $poolCache = null;

    public function __construct(
        private readonly AccessGateService $accessGate,
        private readonly AttemptRepositoryInterface $attempts,
    ) {}

    // ───────────────────────────── Trạng thái ─────────────────────────────

    public static function ready(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('product_assessments') && Schema::hasColumn('assessments', 'bundle_only');
    }

    public function isBundle(?Product $product): bool
    {
        return $product !== null && $product->type === ProductType::Exam;
    }

    public function filtersOf(Product $product): ExamBundleFilters
    {
        return ExamBundleFilters::fromArray($product->bundle_filters ?? null);
    }

    // ───────────────────────────── Tính danh sách đề ─────────────────────────────

    /**
     * Mọi đề luyện tập ĐÃ PHÁT HÀNH (ứng viên của mọi bộ đề) — nhớ trong 1 request.
     *
     * @return Collection<int, Assessment> khoá = id
     */
    public function pool(): Collection
    {
        if ($this->poolCache !== null) {
            return $this->poolCache;
        }

        return $this->poolCache = Assessment::query()
            ->where('type', 'practice')
            ->where('status', ContentStatus::Published->value)
            ->withCount(['items', 'answerKeys', 'codingItems'])
            ->get()
            ->keyBy('id');
    }

    /** @return Collection<int, ProductAssessment> khoá = assessment_id */
    private function rows(Product $product): Collection
    {
        return ProductAssessment::query()->where('product_id', $product->id)->get()->keyBy('assessment_id');
    }

    /**
     * Danh sách đề của bộ, ĐÚNG THỨ TỰ hiển thị.
     *
     * @return Collection<int, Assessment> (list)
     */
    public function items(Product $product): Collection
    {
        if (! self::ready() || ! $this->isBundle($product)) {
            return collect();
        }

        $pool = $this->pool();
        $rows = $this->rows($product);
        $filters = $this->filtersOf($product);
        $locked = (bool) $product->bundle_locked;

        $ids = [];
        foreach ($rows as $assessmentId => $row) {
            if (! $row->excluded && ($row->source === ProductAssessment::SOURCE_MANUAL || $locked)) {
                $ids[$assessmentId] = true;
            }
        }
        if (! $locked && ! $filters->isEmpty()) {
            foreach ($pool as $assessment) {
                if ($filters->matches($assessment) && ! ($rows->get($assessment->id)?->excluded ?? false)) {
                    $ids[$assessment->id] = true;
                }
            }
        }

        return collect(array_keys($ids))
            ->map(fn ($id) => $pool->get($id))
            ->filter()
            ->sort(fn (Assessment $a, Assessment $b) => $this->compare($a, $b, $rows))
            ->values();
    }

    /** Thứ tự: sort_order tay (nhỏ trước) > năm học mới trước > tỉnh A–Z > đề mới trước. */
    private function compare(Assessment $a, Assessment $b, Collection $rows): int
    {
        $sa = $rows->get($a->id)?->sort_order;
        $sb = $rows->get($b->id)?->sort_order;
        if ($sa !== null || $sb !== null) {
            if ($sa === null) {
                return 1;
            }
            if ($sb === null) {
                return -1;
            }
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
        }

        $ya = ExamBundleFilters::yearOf($a->academic_year) ?? 0;
        $yb = ExamBundleFilters::yearOf($b->academic_year) ?? 0;
        if ($ya !== $yb) {
            return $yb <=> $ya;
        }

        $pa = (string) (ProvinceCatalog::label($a->province) ?? '');
        $pb = (string) (ProvinceCatalog::label($b->province) ?? '');
        if ($pa !== $pb) {
            return strcmp($pa, $pb);
        }

        return $b->id <=> $a->id;
    }

    /**
     * Các bộ đề đang chứa đề này (trang chi tiết đề ở admin + kiểm quyền làm đề bundle_only).
     *
     * @return Collection<int, Product>
     */
    public function bundlesContaining(Assessment $assessment): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return Product::query()
            ->where('type', ProductType::Exam->value)
            ->get()
            ->filter(fn (Product $p) => $this->items($p)->contains('id', $assessment->id))
            ->values();
    }

    // ───────────────────────────── Thao tác của admin ─────────────────────────────

    public function saveFilters(Product $product, array $input): ExamBundleFilters
    {
        $filters = ExamBundleFilters::fromArray($input);
        $product->forceFill(['bundle_filters' => $filters->isEmpty() ? null : $filters->toArray()])->save();

        return $filters;
    }

    /** Khoá: chụp danh sách hiện tại vào bảng (để giữ nguyên dù bộ lọc thay đổi / có đề mới). Mở khoá: áp lại bộ lọc. */
    public function setLocked(Product $product, bool $locked): void
    {
        DB::transaction(function () use ($product, $locked) {
            if ($locked) {
                foreach ($this->items($product) as $assessment) {
                    ProductAssessment::query()->firstOrCreate(
                        ['product_id' => $product->id, 'assessment_id' => $assessment->id],
                        ['source' => ProductAssessment::SOURCE_AUTO, 'excluded' => false],
                    );
                }
            }
            $product->forceFill(['bundle_locked' => $locked])->save();
        });
    }

    /** Ghim tay các đề vào bộ (đề luyện tập đã phát hành). @return int số đề thêm được */
    public function addManual(Product $product, array $assessmentIds): int
    {
        $pool = $this->pool();
        $added = 0;

        foreach (array_unique(array_map('intval', $assessmentIds)) as $id) {
            if (! $pool->has($id)) {
                continue;
            }
            ProductAssessment::query()->updateOrCreate(
                ['product_id' => $product->id, 'assessment_id' => $id],
                ['source' => ProductAssessment::SOURCE_MANUAL, 'excluded' => false],
            );
            $added++;
        }

        return $added;
    }

    /** "Bỏ khỏi bộ đề": đề ghim tay mà không khớp bộ lọc thì xoá dòng; còn lại đánh dấu loại (lần sau không tự thêm lại). */
    public function remove(Product $product, int $assessmentId): void
    {
        $row = ProductAssessment::query()->where('product_id', $product->id)->where('assessment_id', $assessmentId)->first();
        $assessment = $this->pool()->get($assessmentId);
        $matchesFilter = $assessment !== null && $this->filtersOf($product)->matches($assessment);

        if ($row !== null && $row->source === ProductAssessment::SOURCE_MANUAL && ! $matchesFilter && ! $product->bundle_locked) {
            $row->delete();

            return;
        }

        ProductAssessment::query()->updateOrCreate(
            ['product_id' => $product->id, 'assessment_id' => $assessmentId],
            ['excluded' => true, 'source' => $row?->source ?? ProductAssessment::SOURCE_AUTO],
        );
    }

    public function restore(Product $product, int $assessmentId): void
    {
        ProductAssessment::query()->where('product_id', $product->id)->where('assessment_id', $assessmentId)->update(['excluded' => false]);
    }

    /** ↑/↓: đổi chỗ với đề liền trên/dưới rồi đánh lại sort_order 10, 20, 30… cho cả danh sách hiện tại. */
    public function move(Product $product, int $assessmentId, string $direction): void
    {
        $ids = $this->items($product)->pluck('id')->all();
        $at = array_search($assessmentId, $ids, true);
        if ($at === false) {
            return;
        }
        $swap = $direction === 'up' ? $at - 1 : $at + 1;
        if (! isset($ids[$swap])) {
            return;
        }
        [$ids[$at], $ids[$swap]] = [$ids[$swap], $ids[$at]];

        DB::transaction(function () use ($product, $ids) {
            foreach ($ids as $i => $id) {
                $row = ProductAssessment::query()->firstOrNew(['product_id' => $product->id, 'assessment_id' => $id]);
                if (! $row->exists) {
                    $row->source = ProductAssessment::SOURCE_AUTO;
                    $row->excluded = false;
                }
                $row->sort_order = ($i + 1) * 10;
                $row->save();
            }
        });
    }

    /** Về thứ tự mặc định (bỏ hết thứ tự tay). */
    public function resetOrder(Product $product): void
    {
        ProductAssessment::query()->where('product_id', $product->id)->update(['sort_order' => null]);
        // Đang mở khoá: dòng "auto" không còn nhớ gì (không loại, không thứ tự) thì dọn cho gọn. Đang khoá thì các dòng
        // đó CHÍNH LÀ danh sách đã chụp — giữ nguyên.
        if (! $product->bundle_locked) {
            ProductAssessment::query()->where('product_id', $product->id)
                ->where('source', ProductAssessment::SOURCE_AUTO)->where('excluded', false)
                ->delete();
        }
    }

    public function setBundleOnly(Assessment $assessment, bool $only): void
    {
        $assessment->forceFill(['bundle_only' => $only])->save();
    }

    // ───────────────────────────── Dữ liệu cho màn hình ─────────────────────────────

    /** @return array<string, mixed> dữ liệu khối "Đề trong bộ đề" ở trang chi tiết tài liệu (admin) */
    public function adminData(Product $product): array
    {
        $items = $this->items($product);
        $rows = $this->rows($product);
        $filters = $this->filtersOf($product);
        $inIds = $items->pluck('id')->all();

        return [
            'ready' => self::ready(),
            'filters' => $filters,
            'locked' => (bool) $product->bundle_locked,
            'items' => $items->map(fn (Assessment $a) => $this->row($a) + [
                'pinned' => ($rows->get($a->id)?->source) === ProductAssessment::SOURCE_MANUAL,
                'byFilter' => ! $product->bundle_locked && $filters->matches($a),
            ])->all(),
            'excluded' => $rows->filter(fn ($r) => $r->excluded)
                ->map(fn ($r) => $this->pool()->get($r->assessment_id))->filter()
                ->map(fn (Assessment $a) => $this->row($a))->values()->all(),
            // ứng viên cho ô "Thêm đề có sẵn": đề luyện tập đã phát hành chưa có trong bộ
            'candidates' => $this->pool()->reject(fn ($a) => in_array($a->id, $inIds, true))
                ->sortByDesc('id')->map(fn (Assessment $a) => $this->row($a))->values()->all(),
            'totalQuestions' => $items->sum(fn ($a) => $this->questionCount($a)),
        ];
    }

    /**
     * Dữ liệu trang bộ đề cho người xem (public/bundles/show).
     *
     * @return array<string, mixed>
     */
    public function viewerData(Product $product, ?User $viewer): array
    {
        $items = $this->items($product);
        $hasAccess = $this->canUseBundle($viewer, $product);
        $progress = $viewer !== null && $items->isNotEmpty()
            ? $this->attempts->progressForUserAndAssessments($viewer->id, $items->pluck('id')->all())->keyBy('assessment_id')
            : collect();

        $rows = $items->map(function (Assessment $a) use ($progress, $hasAccess) {
            $p = $progress->get($a->id);
            $status = match (true) {
                $p !== null && (int) $p->submitted_count > 0 => 'done',
                $p !== null && (int) $p->in_progress_count > 0 => 'doing',
                default => 'todo',
            };

            return $this->row($a) + [
                'status' => $status,
                'bestScore' => $p?->best_score !== null ? round((float) $p->best_score, 2) : null,
                'locked' => (bool) $a->bundle_only && ! $hasAccess,
                'href' => route('practice.exam.show', $a->id),
            ];
        })->all();

        return [
            'product' => $product,
            'items' => $rows,
            'hasAccess' => $hasAccess,
            'doneCount' => count(array_filter($rows, fn ($r) => $r['status'] === 'done')),
            'totalQuestions' => $items->sum(fn ($a) => $this->questionCount($a)),
            'bundleOnlyCount' => $items->where('bundle_only', true)->count(),
        ];
    }

    /** @return array<string, mixed> 1 dòng đề gọn cho giao diện */
    private function row(Assessment $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'code' => $a->exam_code,
            'category' => $a->examCategoryLabel(),
            'level' => $a->examLevelLabel(),
            'province' => $a->provinceLabel(),
            'year' => $a->academic_year,
            'questions' => $this->questionCount($a),
            'points' => $a->total_points,
            'duration' => $a->duration_minutes,
            'bundleOnly' => (bool) $a->bundle_only,
        ];
    }

    private function questionCount(Assessment $a): int
    {
        $items = (int) ($a->items_count ?? 0);

        return $items > 0 ? $items : (int) ($a->answer_keys_count ?? 0) + (int) ($a->coding_items_count ?? 0);
    }

    // ───────────────────────────── Quyền ─────────────────────────────

    /** Đã mua bộ đề: quyền cá nhân còn hạn, hoặc lớp đang gắn cả tài liệu, hoặc admin. (Không tính "Công khai" — bộ đề phải mua.) */
    public function canUseBundle(?User $user, Product $product): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN)
            || $this->accessGate->hasActivePersonalAccess($user, $product->id)
            || $this->accessGate->hasActiveClassGrantedAccess($user, $product->id);
    }

    /** Làm được đề này không: đề công khai luôn được; đề "Chỉ trong bộ đề" cần quyền một bộ đề chứa nó (hoặc được giao). */
    public function canTake(User $user, Assessment $assessment, ?Assignment $assignment = null): bool
    {
        if (! self::ready() || ! $assessment->bundle_only || $assignment !== null) {
            return true;
        }
        if ($user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            return true;
        }

        return $this->bundlesContaining($assessment)->contains(fn (Product $p) => $this->canUseBundle($user, $p));
    }

    /** @throws ValidationException (key 'attempt' — màn "không mở được lượt làm" sẵn có hiện câu này) */
    public function assertCanTake(User $user, Assessment $assessment, ?Assignment $assignment = null): void
    {
        if ($this->canTake($user, $assessment, $assignment)) {
            return;
        }

        $names = $this->bundlesContaining($assessment)->pluck('title')->take(3)->implode('", "');
        throw ValidationException::withMessages([
            'attempt' => 'Đề này chỉ dành cho người đã mua bộ đề'.($names !== '' ? ' "'.$names.'"' : '').'. Mua bộ đề ở trang Tài liệu để làm đề.',
        ]);
    }

    // ───────────────────────────── Gói ZIP đề ─────────────────────────────

    /**
     * Đọc 2 khoá tuỳ chọn trong exam.json của gói đề vừa nhập (ExamZipImportService):
     *   "exam": { "visibility": "bundle_only", "bundles": ["<mã slug hoặc id bộ đề>", ...] }
     * Đề -> "Chỉ trong bộ đề" và/hoặc ghim vào các bộ đề đó. Mã không tìm thấy thì bỏ qua và báo lại.
     *
     * @return string dòng tóm tắt thêm vào thông báo nhập ('' nếu gói không khai gì)
     */
    public function applyZipOptions(Assessment $assessment, string $zipPath): string
    {
        if (! self::ready()) {
            return '';
        }

        $exam = $this->readExamJson($zipPath)['exam'] ?? null;
        if (! is_array($exam)) {
            return '';
        }

        $notes = [];
        if (($exam['visibility'] ?? null) === 'bundle_only') {
            $this->setBundleOnly($assessment, true);
            $notes[] = 'đề "Chỉ trong bộ đề"';
        }

        $codes = array_values(array_filter(array_map('strval', is_array($exam['bundles'] ?? null) ? $exam['bundles'] : [])));
        if ($codes !== []) {
            $this->poolCache = null; // đề vừa tạo có thể chưa có trong pool đã nhớ
            $found = [];
            foreach ($codes as $code) {
                $product = Product::query()->where('type', ProductType::Exam->value)
                    ->where(fn ($q) => $q->where('slug', $code)->orWhere('id', ctype_digit($code) ? (int) $code : 0))
                    ->first();
                if ($product === null) {
                    $notes[] = 'không thấy bộ đề "'.$code.'"';

                    continue;
                }
                ProductAssessment::query()->updateOrCreate(
                    ['product_id' => $product->id, 'assessment_id' => $assessment->id],
                    ['source' => ProductAssessment::SOURCE_MANUAL, 'excluded' => false],
                );
                $found[] = $product->title;
            }
            if ($found !== []) {
                $notes[] = 'gắn vào bộ đề: '.implode(', ', $found);
            }
        }

        return $notes === [] ? '' : 'Bộ đề: '.implode('; ', $notes).'.';
    }

    /** exam.json ở gốc gói hoặc trong 1 thư mục bọc ngoài (cùng quy ước ExamZipPackage). */
    private function readExamJson(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [];
        }

        $raw = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^([^/]+/)?exam\.json$#i', $name)) {
                $raw = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();

        $json = is_string($raw) ? json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $raw), true) : null;

        return is_array($json) ? $json : [];
    }
}
