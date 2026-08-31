<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Concerns\Auditable;
use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\QuestionType;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    /** Đọc bởi App\Concerns\Auditable — lý do khi admin publish/từ chối/lưu trữ câu hỏi (6.2, 10.4). */
    public static ?string $auditReason = null;

    protected $fillable = [
        'bank_id', 'code', 'type', 'title', 'body', 'points', 'grading_config', 'metadata',
        'owner_type', 'owner_id', 'visibility', 'status', 'version', 'parent_version_id', 'created_by',
        // SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — product_id khác null nghĩa là câu hỏi
        // này là bài tập riêng của 1 sản phẩm, không thuộc Kho câu hỏi dùng chung. Mọi nơi lấy
        // câu hỏi CHUNG phải whereNull('product_id'), xem QuestionRepository.
        'product_id',
    ];

    protected $casts = [
        'type' => QuestionType::class,
        'owner_type' => OwnerType::class,
        'visibility' => Visibility::class,
        'status' => ContentStatus::class,
        'grading_config' => 'array',
        'metadata' => 'array',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'bank_id');
    }

    /** SỬA 31/8 — sản phẩm sở hữu câu hỏi này (khi đây là "bài tập đính kèm sản phẩm"). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'parent_version_id');
    }

    public function assessmentItems(): HasMany
    {
        return $this->hasMany(AssessmentItem::class);
    }

    /** SỬA 19/8 (Giai đoạn 6) — chuyên đề/tag gắn cho câu hỏi, xem App\Models\Tag. */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'question_tag');
    }

    /**
     * Điều kiện tối thiểu để publish theo loại câu (6.2). Đây chỉ là kiểm tra cấu trúc dữ liệu;
     * quy tắc đầy đủ (không sửa âm thầm câu đã có người làm, v.v.) nằm ở QuestionPublishGuard.
     */
    public function hasMinimumGradingConfig(): bool
    {
        $config = $this->grading_config ?? [];

        return match ($this->type) {
            QuestionType::Coding => filled($config['test_cases'] ?? null)
                && filled($config['time_limit_ms'] ?? null)
                && filled($config['memory_limit_mb'] ?? null),
            QuestionType::Mcq => filled($config['correct_options'] ?? null) && $this->points > 0,
            QuestionType::FillBlank => filled($config['accepted_answers'] ?? null) && $this->points > 0,
        };
    }
}
