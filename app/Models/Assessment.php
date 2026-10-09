<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Concerns\Auditable;
use App\Enums\AssessmentContentMode;
use App\Enums\AssessmentType;
use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\PublishAnswerRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    /** Đọc bởi App\Concerns\Auditable — lý do khi admin publish/từ chối/lưu trữ đề (10.4). */
    public static ?string $auditReason = null;

    protected $fillable = [
        'title', 'type', 'total_points', 'duration_minutes', 'resubmission_policy',
        'publish_answer_rule', 'status', 'version', 'owner_type', 'owner_id', 'created_by',
        // SỬA 18/8 (đề PDF + phiếu đáp án) — xem App\Enums\AssessmentContentMode.
        'content_mode', 'exam_code', 'pdf_path', 'pdf_original_name', 'solution_pdf_path',
        'preview_page_from', 'preview_page_to',
        // SỬA 2/10 — tệp PDF XEM TRƯỚC riêng của đề (người ra đề tự tải lên), xem migration
        // add_preview_pdf_to_assessments_table.
        'preview_pdf_path', 'preview_pdf_original_name',
        // SỬA 2/10 — 6 trường mô tả đề cho màn chi tiết + thẻ đề ngoài trang công khai, xem
        // migration add_detail_fields_to_assessments_table.
        'subtitle', 'author', 'province', 'academic_year', 'exam_category', 'cover_image_path',
        // SỬA 7/10 — độ khó 1-5 của đề (cùng thang với câu hỏi), xem migration
        // add_difficulty_and_ratings_to_assessments.
        'difficulty_level',
        // SỬA 7/10 (khách: "số sao đánh giá cho nhập tay") — điểm sao + số lượt do admin/giáo viên nhập
        // tay; gộp với lượt chấm thật của học sinh khi hiển thị, xem PracticeService::combinedRating().
        'rating_score', 'rating_count',
    ];

    protected $casts = [
        'rating_score' => 'float',
        // SỬA 11/10 — tổng điểm có thể là số thập phân (cột decimal trả chuỗi "26.00" nếu không cast).
        'total_points' => 'float',
        'rating_count' => 'integer',
        'type' => AssessmentType::class,
        'status' => ContentStatus::class,
        'owner_type' => OwnerType::class,
        'publish_answer_rule' => PublishAnswerRule::class,
        'resubmission_policy' => 'array',
        'content_mode' => AssessmentContentMode::class,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentItem::class)->orderBy('order');
    }

    /**
     * SỬA 11/10 (khách: "tổng điểm của đề lấy điểm từng câu tổng lại khi chấm điểm") — TỔNG ĐIỂM
     * ĐỀ = cộng điểm từng câu NGƯỜI DÙNG NHẬP (assessment_items.points_override). Gọi lúc chấm bài /
     * xem kết quả để cột total_points luôn khớp với điểm các câu — đề cũ lệch số thì tự sửa lại.
     * Đề không có câu nào (đề PDF…) giữ nguyên số đang có. Trả về tổng điểm hiện hành.
     */
    public function syncTotalPointsFromItems(): float
    {
        $this->loadMissing('items.question');

        if ($this->items->isEmpty()) {
            return (float) $this->total_points;
        }

        $sum = \App\Support\AssessmentPoints::sum(
            $this->items->map(fn ($item) => $item->points_override ?? $item->question?->points ?? 0)
        );

        if (abs($sum - (float) $this->total_points) >= 0.005) {
            $this->forceFill(['total_points' => $sum])->save();
        }

        return $sum;
    }

    /** SỬA 2/10 — nhãn tỉnh/thành ("HANOI" -> "Hà Nội"); chưa gán hoặc mã lạ -> null. */
    public function provinceLabel(): ?string
    {
        return \App\Support\ProvinceCatalog::label($this->province);
    }

    /** SỬA 7/10 — khu vực (Miền Bắc/Trung/Nam) suy ra từ tỉnh/thành; chưa gán -> null. */
    public function regionLabel(): ?string
    {
        return \App\Support\ProvinceCatalog::region($this->province);
    }

    /** SỬA 7/10 — độ khó 1-5 hợp lệ hoặc null (chưa xếp). */
    public function difficultyStars(): ?int
    {
        $level = (int) $this->difficulty_level;

        return $level >= 1 && $level <= 5 ? $level : null;
    }

    /** SỬA 7/10 — nhãn độ khó cùng bộ nhãn với câu hỏi ("Cơ bản"…"Rất khó"); chưa xếp -> null. */
    public function difficultyLabel(): ?string
    {
        $level = $this->difficultyStars();

        return $level === null ? null : \App\Support\QuestionDifficulty::label(array_search($level, \App\Support\QuestionDifficulty::STARS, true) ?: null);
    }

    /** SỬA 2/10 — nhãn loại đề ("hsg_quoc_gia" -> "HSG Quốc gia"); chưa gán -> null. */
    public function examCategoryLabel(): ?string
    {
        return \App\Support\ExamCategory::label($this->exam_category);
    }

    /**
     * SỬA 2/10 — ảnh bìa đề. Trả null khi chưa có ảnh riêng; nơi gọi tự vẽ khối thay thế chứ
     * KHÔNG mượn ảnh của thứ khác (thẻ đề trước đây lấy ảnh sách theo số thứ tự, nhìn như đề có
     * ảnh riêng mà thật ra không phải).
     */
    public function coverUrl(): ?string
    {
        // SỬA 10/10 — hiểu cả ảnh chọn từ catalog ("catalog:<id>") lẫn ảnh tải lên.
        return \App\Support\AssessmentCover::url($this->cover_image_path);
    }

    /** Các material (chương/mục) trong sách/chuyên đề trỏ tới đề này qua type=assessment_ref. */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'assessment_id');
    }

    public function questions()
    {
        return $this->items()->with('question');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * SỬA 18/8 — đáp án đúng từng câu của đề PDF (content_mode = pdf_answer_sheet), đánh số
     * theo đúng thứ tự in trên đề. Rỗng nếu đề dùng content_mode = structured.
     */
    public function answerKeys(): HasMany
    {
        return $this->hasMany(AssessmentAnswerKey::class)->orderBy('question_no');
    }

    /**
     * SỬA 18/8 — các bài lập trình con trong đề PDF (nếu có). Một đề PDF có thể vừa có
     * answerKeys() (trắc nghiệm/đúng-sai/trả lời ngắn) vừa có codingItems() cùng lúc.
     */
    public function codingItems(): HasMany
    {
        return $this->hasMany(AssessmentCodingItem::class);
    }

    public function isPdfMode(): bool
    {
        return $this->content_mode === AssessmentContentMode::PdfAnswerSheet;
    }
}
