<?php

namespace App\Repositories\Eloquent;

use App\Models\Question;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Support\QuestionDifficulty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class QuestionRepository extends EloquentRepository implements QuestionRepositoryInterface
{
    protected string $modelClass = Question::class;

    public function byOwner(int $ownerId, ?string $status = null, int $limit = 50): Collection
    {
        $query = $this->query()->where('owner_id', $ownerId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->latest()->limit($limit)->get();
    }

    public function countByOwner(int $ownerId, ?string $status = null): int
    {
        $query = $this->query()->where('owner_id', $ownerId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    public function sharedLatestWithOwner(int $limit = 50): Collection
    {
        // SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — loại câu hỏi là "bài tập đính kèm sản
        // phẩm" (product_id khác null) khỏi Kho câu hỏi dùng chung, tránh lộ nội dung trả phí
        // ra chỗ chung.
        return $this->query()->with('owner')->where('owner_type', 'shared')->whereNull('product_id')->latest()->limit($limit)->get();
    }

    public function countShared(): int
    {
        return $this->query()->where('owner_type', 'shared')->whereNull('product_id')->count();
    }

    /** Admin xem được toàn bộ câu hỏi (Kho chung + kho riêng từng giáo viên) — không lọc
     *  owner_type, nhưng vẫn loại bài tập riêng của sản phẩm (product_id khác null) — mục đó
     *  quản lý ở trang Sản phẩm (Admin\ProductExerciseController), không hiện ở Kho câu hỏi. */
    public function allLatestWithOwner(int $limit = 50): Collection
    {
        return $this->query()->with('owner')->whereNull('product_id')->latest()->limit($limit)->get();
    }

    /**
     * SỬA 8/9 (3) ("phân loại kho câu hỏi theo môn") — bản CÓ LỌC của allLatestWithOwner().
     * Giữ nguyên 2 ràng buộc gốc của Kho câu hỏi (whereNull('product_id') — bài tập riêng của
     * sản phẩm quản lý ở trang Sản phẩm; sắp xếp mới nhất trước), chỉ chồng thêm điều kiện lọc.
     */
    public function allWithOwnerFiltered(array $filters, int $limit = 50): Collection
    {
        return $this->applyQuestionBankFilters($this->query()->with('owner'), $filters)
            ->latest()->limit($limit)->get();
    }

    public function countAllFiltered(array $filters): int
    {
        return $this->applyQuestionBankFilters($this->query(), $filters)->count();
    }

    /**
     * SỬA 8/9 (3) — 1 truy vấn GROUP BY duy nhất cho cả bảng đếm theo môn, thay vì bắn N câu
     * count() cho N môn. Câu chưa gán môn (subject IS NULL) gom về khoá ''.
     */
    public function countsBySubject(array $scope = []): array
    {
        return $this->query()
            ->whereNull('product_id')
            // SỬA 18/9 — $scope = ['owner_id' => …] hoặc ['owner_type' => 'shared'] để chip đếm
            // theo môn ở trang giáo viên chỉ đếm ĐÚNG kho đang xem. Admin gọi không tham số ->
            // đếm toàn bộ như trước, không đổi hành vi cũ.
            ->when(! empty($scope['owner_id']), fn (Builder $q) => $q->where('owner_id', (int) $scope['owner_id']))
            ->when(! empty($scope['owner_type']), fn (Builder $q) => $q->where('owner_type', $scope['owner_type']))
            ->selectRaw('subject, COUNT(*) as aggregate')
            ->groupBy('subject')
            ->pluck('aggregate', 'subject')
            ->mapWithKeys(fn ($count, $subject) => [(string) ($subject ?? '') => (int) $count])
            ->all();
    }

    /**
     * SỬA 8/9 (3) — điểm DUY NHẤT hiểu mảng $filters (xem QuestionRepositoryInterface), để 2
     * hàm trên (lấy danh sách / đếm tổng) không bao giờ lệch điều kiện nhau.
     */
    private function applyQuestionBankFilters(Builder $query, array $filters): Builder
    {
        $query->whereNull('product_id');

        // SỬA 18/9 (khách: "kho câu hỏi của tôi bên giáo viên hiển thêm phần lọc cho đầy đủ như
        // admin") — 2 khoá PHẠM VI để giáo viên dùng LẠI đúng hàm lọc này thay vì viết bản thứ
        // hai rồi lệch luật: 'owner_id' = kho riêng của 1 giáo viên, 'owner_type' = "shared"
        // cho tab "Kho chung (chỉ xem)". Admin không truyền khoá nào -> xem toàn bộ như trước.
        if (! empty($filters['owner_id'])) {
            $query->where('owner_id', (int) $filters['owner_id']);
        }

        if (! empty($filters['owner_type'])) {
            $query->where('owner_type', $filters['owner_type']);
        }

        $subject = $filters['subject'] ?? null;
        if ($subject === 'none') {
            $query->whereNull('subject');
        } elseif (is_string($subject) && $subject !== '') {
            $query->where('subject', $subject);
        }

        $grade = $filters['grade'] ?? null;
        if ($grade === 'none') {
            $query->whereNull('grade');
        } elseif ($grade !== null && $grade !== '') {
            $query->where('grade', (int) $grade);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            // escape ký tự đại diện của LIKE để admin tìm chuỗi có '%' hoặc '_' không ra kết
            // quả rác (vd mã câu hỏi có gạch dưới: "TOAN6_..." — '_' vốn khớp 1 ký tự bất kỳ).
            $escaped = addcslashes($keyword, '%_\\');
            $query->where(function (Builder $sub) use ($escaped) {
                $sub->where('title', 'like', '%'.$escaped.'%')
                    ->orWhere('code', 'like', '%'.$escaped.'%');
            });
        }

        $this->applyDifficultyFilter($query, $filters['difficulty'] ?? null);

        return $query;
    }

    /**
     * SỬA 18/9 (khách: "chỗ giáo viên và admin thêm lọc theo độ khó nữa nha") — lọc theo độ khó.
     *
     * Khó ở chỗ độ khó KHÔNG phải 1 cột: câu đã đặt thì nằm ở metadata.difficulty, câu chưa đặt
     * thì SUY theo điểm (xem App\Support\QuestionDifficulty — cùng lớp mà trang Luyện tập dùng
     * để hiển thị). Nếu chỉ so metadata thì lọc "Dễ" sẽ bỏ sót toàn bộ câu chưa đặt, dù ngoài
     * trang Luyện tập chúng vẫn đang hiện chữ "Dễ" — người dùng sẽ thấy lọc bị "mất bài".
     * Nên mỗi mức là phép HOẶC của 2 vế:
     *   (1) đã đặt đúng mức đó (khoá mới, hoặc số 1-5 kiểu cũ);
     *   (2) CHƯA đặt (thiếu khoá / giá trị lạ / JSON null) VÀ điểm rơi vào khoảng của mức đó.
     * Khoảng điểm lấy từ POINT_RANGES — suy ngược từ đúng công thức derive(), nên 2 chỗ không lệch.
     *
     * Giá trị 'none' = "chưa ai đặt độ khó", để admin/giáo viên dò ra mà gán dần.
     */
    private function applyDifficultyFilter(Builder $query, mixed $difficulty): void
    {
        if ($difficulty === null || $difficulty === '') {
            return;
        }

        $column = 'metadata->difficulty';
        $stored = QuestionDifficulty::allStoredValues();

        if ($difficulty === QuestionDifficulty::UNSET) {
            $query->where(fn (Builder $sub) => $sub->whereNull($column)->orWhereNotIn($column, $stored));

            return;
        }

        if (! QuestionDifficulty::isValidKey($difficulty)) {
            return; // khoá lạ (link bị sửa tay) -> coi như không lọc, không trả bảng rỗng khó hiểu
        }

        [$minPoints, $maxPoints] = QuestionDifficulty::POINT_RANGES[$difficulty];

        $query->where(function (Builder $sub) use ($column, $difficulty, $stored, $minPoints, $maxPoints) {
            $sub->whereIn($column, QuestionDifficulty::storedValuesFor($difficulty))
                ->orWhere(function (Builder $derived) use ($column, $stored, $minPoints, $maxPoints) {
                    $derived
                        ->where(fn (Builder $unset) => $unset->whereNull($column)->orWhereNotIn($column, $stored))
                        ->whereBetween('points', [$minPoints, $maxPoints]);
                });
        });
    }

    /**
     * SỬA 24/8 (v2) — khách chốt: "Luyện tập theo câu" dùng CẢ câu hỏi thuộc kho riêng giáo
     * viên, không chỉ Kho chung nữa — bỏ hẳn điều kiện where('owner_type', 'shared'). "Đã
     * phát hành" (status=published) vẫn là điều kiện chặn duy nhất còn lại — giáo viên phải tự
     * bấm Phát hành ở câu đó thì câu mới vào được vòng luyện công khai này, câu Nháp không bao
     * giờ lọt ra dù thuộc kho ai. Cố ý KHÔNG lọc theo cột 'visibility' (câu giáo viên tạo mặc
     * định visibility=private, xem Teacher\QuestionService::store() — lọc thêm điều kiện đó sẽ
     * loại sạch mọi câu giáo viên, ngược lại đúng yêu cầu khách vừa chốt).
     * SỬA 24/8 (v4) — khách chốt: thêm dạng 'coding' vào luôn — hệ thống vẫn CHƯA có sandbox
     * chấm code thật, nên Student\PracticeByQuestionService::answer() không tự chấm đúng/sai
     * cho câu Lập trình (chỉ ghi nhận bài làm, giống quy ước "Queued" của AttemptService) —
     * đây chỉ là nơi LẤY câu, không phải nơi quyết định có chấm được hay không.
     * SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — QUAN TRỌNG: thêm whereNull('product_id') —
     * nếu không, bài tập Lập trình RIÊNG của 1 sản phẩm (trả phí) sẽ lọt ra vòng luyện tập
     * CÔNG KHAI/MIỄN PHÍ này ngay khi admin bấm "Lưu bài tập" (status -> published), lộ mất nội
     * dung trả phí cho người chưa mua. Bài tập sản phẩm CHỈ làm được qua "Tài liệu của tôi" (đã
     * kiểm tra quyền sở hữu, xem AccessGateService::canAccessProduct()).
     */
    public function idsForPractice(?string $type, array $tagIds): array
    {
        return $this->query()
            ->where('status', 'published')
            ->whereNull('product_id')
            // SỬA 31/8 (2, "mở rộng ZIP bài tập" nhiều dạng câu) — thêm 'composite' vào pool
            // luyện tập công khai: câu Composite tạo qua nhập ZIP vào Kho chung (không phải bài
            // tập sản phẩm) vẫn phải lọt ra đây như 3 dạng cũ, xem Student\
            // PracticeByQuestionService::gradeCompositeParts() (nơi chấm từng phần cho luồng
            // luyện tập này).
            ->whereIn('type', ['mcq', 'fill_blank', 'coding', 'composite'])
            ->when($type, fn (Builder $q) => $q->where('type', $type))
            ->when($tagIds !== [], fn (Builder $q) => $q->whereHas('tags', fn (Builder $qq) => $qq->whereIn('tags.id', $tagIds)))
            ->pluck('id')
            ->all();
    }
}
