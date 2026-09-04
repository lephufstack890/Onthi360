<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Concerns\Auditable;
use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\ProductType;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    /** Đọc bởi App\Concerns\Auditable — lý do xóa mềm/lưu trữ sản phẩm (10.4, 16 mục 4). */
    public static ?string $auditReason = null;

    protected $fillable = [
        'type', 'title', 'slug', 'description', 'cover_image_path', 'subject', 'grade', 'topic',
        'price', 'price_teaching', 'has_print_option', 'status', 'visibility', 'owner_type', 'owner_id',
        'created_by', 'duration_months',
        // SỬA 27/8 ("4 file đính kèm sản phẩm", đủ 4 ô sau khi bỏ khối "Học liệu thuộc sản
        // phẩm" — cây chương/mục Material cũ): content_pdf = file PDF nội dung chính (thay
        // Material), 3 cột còn lại là tài nguyên phụ.
        'content_pdf_path', 'content_pdf_original_name',
        'guide_pdf_path', 'guide_pdf_original_name',
        'exercise_zip_path', 'exercise_zip_original_name',
        'media_path', 'media_original_name',
    ];

    protected $casts = [
        'type' => ProductType::class,
        'status' => ContentStatus::class,
        'visibility' => Visibility::class,
        'owner_type' => OwnerType::class,
        'has_print_option' => 'boolean',
        'price' => 'integer',
        'price_teaching' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class)->whereNull('parent_id')->orderBy('order');
    }

    /**
     * SỬA 4/9 (khách yêu cầu "Chương/Phần/Đề") — danh sách "chương" (Sách) / "phần" (Chuyên
     * đề) / "đề" (Bộ đề) của sản phẩm này, tái dùng Material (type=chapter) có sẵn — xem
     * Material::scopeChapters(), ProductType::chapterLabel(), ContentService::
     * productChaptersFor().
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Material::class)->chapters()->orderBy('order');
    }

    /** Nhãn hiển thị "Chương"/"Phần"/"Đề" tuỳ loại sản phẩm — null nếu loại này (vd Khóa học) không dùng khái niệm này. */
    public function chapterLabel(): ?string
    {
        return $this->type->chapterLabel();
    }

    public function accessRights(): HasMany
    {
        return $this->hasMany(AccessRight::class);
    }

    /**
     * SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — danh sách bài tập (Question, type=coding)
     * riêng của sản phẩm này, nhập từ gói ZIP OT360-QPACK. CHỈ admin quản lý (thêm/sửa/xoá),
     * không hiện trong Kho câu hỏi/luyện tập dùng chung. Xem ContentService::productExercise*().
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Question::class)->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }
}
