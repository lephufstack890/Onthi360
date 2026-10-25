<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * Kỳ thi (đề) bên trong 1 Competition — 1 Competition có thể gồm NHIỀU kỳ thi (vd: Vòng 1,
 * Vòng 2...), mỗi kỳ thi tham chiếu 1 Assessment riêng. Khác với Competition::assessment_id
 * (đề tham chiếu DUY NHẤT cũ, giữ để tương thích ngược — xem migration backfill
 * ..._create_competition_exams_table.php) — kỳ thi cho phép nhiều đề trong cùng 1 cuộc thi,
 * mỗi kỳ thi có bảng xếp hạng riêng (LeaderboardEntry scope=competition_exam) ngoài bảng
 * tổng (scope=competition, xem CompetitionService::recomputeAggregateFromExams()).
 */
class CompetitionExam extends Model
{
    protected $fillable = ['competition_id', 'assessment_id', 'title', 'order', 'starts_at', 'ends_at', 'assignment_mode', 'form_count',
        'organizer_id', 'source_type']; // SỬA 25/10 — người chủ trì + nguồn đề (migration 001290)

    /** SỬA 25/10 — nguồn đề của kỳ: lấy đề có sẵn trong ngân hàng / thiết lập ma trận từ nguồn câu hỏi (chỉ khảo sát). */
    public const SOURCE_EXAM = 'exam';

    public const SOURCE_QUESTIONS = 'questions';

    /** SỬA 24/10 — cách giao mã đề hoán vị: hệ thống bốc ngẫu nhiên / học sinh tự chọn (xem App\Support\ContestForms). */
    public const MODE_RANDOM = 'random';

    public const MODE_CHOICE = 'choice';

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function leaderboardEntries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /** now() có nằm trong [starts_at, ends_at] không — cột nào null thì bỏ qua điều kiện đó. */
    public function isOngoing(): bool
    {
        return $this->computedStatus() === 'ongoing';
    }

    /**
     * Trạng thái kỳ thi TÍNH THEO GIỜ HIỆN TẠI — dùng để hiển thị rõ ràng cho học sinh (trang
     * chi tiết cuộc thi công khai): 'upcoming' (chưa tới giờ bắt đầu) | 'ended' (đã qua giờ
     * kết thúc) | 'ongoing' (đang trong khung giờ thi, hoặc chưa đặt đủ starts_at/ends_at nên
     * không có gì để chặn — giữ đúng hành vi cũ của isOngoing() khi kỳ thi "chưa đặt lịch cụ
     * thể"). Cột nào null thì bỏ qua điều kiện đó (giống App\Services\Public\
     * CompetitionService's private isWithinWindow(), áp cho từng kỳ thi thay vì cả cuộc thi).
     */
    public function computedStatus(): string
    {
        $now = now();

        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return 'upcoming';
        }

        if ($this->ends_at !== null && $now->gt($this->ends_at)) {
            return 'ended';
        }

        return 'ongoing';
    }

    /** SỬA 25/10 — giáo viên / admin CHỦ TRÌ kỳ (người tạo kỳ trong phòng thi). Null = kỳ cũ, do ban tổ chức cuộc thi quản lý. */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /** SỬA 25/10 — đã chạy migration 001290 (organizer_id, source_type) chưa. */
    public static function supportsRoundSetup(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('competition_exams', 'source_type');
    }

    /**
     * SỬA 25/10 — nguồn đề: cuộc thi luôn "đề có sẵn"; khảo sát theo cột source_type, kỳ cũ chưa ghi thì suy ra:
     * có đề ma trận → nguồn câu hỏi; có đề gắn sẵn → đề có sẵn; chưa có gì → nguồn câu hỏi.
     */
    public function sourceType(?Competition $competition = null): string
    {
        $competition ??= $this->competition;
        if ($competition !== null && ! $competition->isSurvey()) {
            return self::SOURCE_EXAM;
        }
        if (self::supportsRoundSetup() && in_array($this->source_type, [self::SOURCE_EXAM, self::SOURCE_QUESTIONS], true)) {
            return $this->source_type;
        }
        if (CompetitionExamPaper::supported() && $this->papers()->exists()) {
            return self::SOURCE_QUESTIONS;
        }

        return $this->assessment_id !== null ? self::SOURCE_EXAM : self::SOURCE_QUESTIONS;
    }

    /** SỬA 24/10 — các đề sinh từ ma trận của kỳ (tab "Thiết lập đề"), xem App\Models\CompetitionExamPaper. */
    public function papers(): HasMany
    {
        return $this->hasMany(CompetitionExamPaper::class)->orderBy('id');
    }

    /** SỬA 24/10 — đã chạy migration 001260 chưa. */
    public static function supportsForms(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('competition_exams', 'form_count');
    }

    /** Số mã đề hoán vị (1 = không hoán vị). */
    public function formCount(): int
    {
        return self::supportsForms() ? max(1, min(\App\Support\ContestForms::MAX, (int) ($this->form_count ?? 1))) : 1;
    }

    public function assignmentMode(): string
    {
        return self::supportsForms() && $this->assignment_mode === self::MODE_CHOICE ? self::MODE_CHOICE : self::MODE_RANDOM;
    }

    /**
     * SỬA 25/10 (khách: "1 kỳ chọn được nhiều đề, học sinh làm thì random 1 đề") — các đề ngân hàng của kỳ theo thứ tự
     * chọn; đề đầu luôn là assessment_id. Kỳ cũ / chưa chạy migration 001300 → chỉ [assessment_id]. Danh sách lệch với
     * assessment_id (đề bị đổi ở chỗ khác) thì coi như kỳ chỉ còn assessment_id.
     *
     * @return array<int, int>
     */
    public function bankAssessmentIds(): array
    {
        if ($this->assessment_id === null) {
            return [];
        }
        $main = (int) $this->assessment_id;
        if ($this->id === null || ! CompetitionExamAssessment::supported()) {
            return [$main];
        }
        $ids = CompetitionExamAssessment::query()->where('competition_exam_id', $this->id)
            ->orderBy('position')->orderBy('id')->pluck('assessment_id')->map(fn ($v) => (int) $v)->all();

        return in_array($main, $ids, true) ? array_values(array_unique(array_merge([$main], $ids))) : [$main];
    }

    /** SỬA 25/10 — lưu danh sách đề ngân hàng của kỳ (≤ 1 đề thì không cần dòng nào). assessment_id do người gọi đặt. */
    public function syncBankAssessments(array $ids): void
    {
        if (! CompetitionExamAssessment::supported()) {
            return;
        }
        CompetitionExamAssessment::query()->where('competition_exam_id', $this->id)->delete();
        if (count($ids) <= 1) {
            return;
        }
        foreach (array_values($ids) as $i => $id) {
            CompetitionExamAssessment::query()->create(['competition_exam_id' => $this->id, 'assessment_id' => (int) $id, 'position' => $i]);
        }
    }

    /** title rỗng (đa số kỳ thi backfill từ cuộc thi cũ) thì hiển thị tên đề tham chiếu. */
    public function displayTitle(): string
    {
        return $this->title ?: ($this->assessment->title ?? 'Kỳ thi #'.$this->id);
    }
}
