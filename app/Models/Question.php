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
            // SỬA 31/8 (2) — câu Composite CHỈ tạo được qua nhập ZIP (không có form nhập tay
            // tương ứng, xem ContentService::questionUpdate()/questionCreateNewVersion() —
            // grading_config giữ NGUYÊN từ lúc nhập, không qua buildGradingConfig()) — điều
            // kiện tối thiểu ở đây chỉ là có ít nhất 1 phần con và có điểm, không kiểm tra sâu
            // từng phần (gói ZIP đã tự đảm bảo cấu trúc từng phần lúc nhập).
            QuestionType::Composite => filled($config['parts'] ?? null) && $this->points > 0,
        };
    }

    /**
     * SỬA 31/8 (2) — mô tả ngắn gọn cấu hình chấm, dùng ở mọi nơi hiển thị danh sách "Bài tập"
     * (LibraryService::exercisesFor(), ContentService::productExercisesFor()) THAY cho
     * "X test case" cứng cho mọi loại như trước — trước đây chỉ đúng với câu Lập trình, giờ
     * bài tập có thể là bất kỳ dạng nào trong 4 dạng ZIP hỗ trợ (xem ContentService::
     * SUPPORTED_ZIP_CONTENT_TYPES).
     */
    public function exerciseSummaryLabel(): string
    {
        $config = $this->grading_config ?? [];

        return match ($this->type) {
            QuestionType::Coding => count($config['test_cases'] ?? []).' test case',
            QuestionType::Mcq => count($config['options'] ?? []).' phương án',
            QuestionType::FillBlank => count($config['accepted_answers'] ?? []).' đáp án chấp nhận',
            QuestionType::Composite => count($config['parts'] ?? []).' phần',
        };
    }

    /**
     * SỬA 31/8 (2) — tìm 1 asset (audio/ảnh...) đính kèm theo id, đọc từ metadata.assets (xem
     * ContentService::storeZipAssets()) — dùng ở route phục vụ tệp cho học sinh nghe/xem lúc
     * "Làm bài" (Student\PracticeByQuestionController::asset()). null nếu không có asset nào
     * khớp id (asset id sai, hoặc câu hỏi này không có asset nào).
     *
     * @return array{path:string, filename:string, kind:string}|null
     */
    public function findAsset(string $assetId): ?array
    {
        foreach (($this->metadata['assets'] ?? []) as $asset) {
            if (($asset['id'] ?? null) === $assetId && isset($asset['path'])) {
                return [
                    'path' => $asset['path'],
                    'filename' => $asset['filename'] ?? basename($asset['path']),
                    'kind' => $asset['kind'] ?? 'file',
                ];
            }
        }

        return null;
    }
}
