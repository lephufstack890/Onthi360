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
    ];

    protected $casts = [
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
