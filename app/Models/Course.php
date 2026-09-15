<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Concerns\Auditable;
use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Course extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'description', 'cover_image_path', 'subject', 'grade', 'status', 'created_by',
        // SỬA 15/9 — bốn trường "bậc" khi khoá học nằm trong một lộ trình, xem migration
        // add_level_fields_to_courses_table.
        'level_code', 'level_subtitle', 'outcome', 'session_count',
        // C1 — sản phẩm loại 'course' dùng để bán khoá này, xem migration
        // add_product_id_to_courses_table.
        'product_id',
    ];

    protected $casts = [
        'status' => ContentStatus::class,
        'session_count' => 'integer',
    ];

    /**
     * Đọc bởi App\Concerns\Auditable — set trước khi delete() để ghi lý do xóa mềm
     * (10.4: "xóa mềm phải có lý do, người thao tác, thời gian và audit log"), xem
     * App\Services\Admin\CourseService::destroy().
     */
    public static ?string $auditReason = null;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classRooms(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    /**
     * Sản phẩm dùng để bán khoá học này (loại 'course').
     *
     * Null nghĩa là khoá chưa mở bán trực tuyến — vẫn vào được bằng mã lớp như trước.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Bản cài đặt này đã chạy migration add_product_id_to_courses_table chưa.
     *
     * CHẶN LỖI LÚC TRIỂN KHAI — cùng một bài học với ContactService::onlyExistingColumns():
     * mã nguồn mới lên máy chủ trước, `php artisan migrate` chạy sau. Trong khoảng giữa đó
     * cột courses.product_id chưa tồn tại, mà màn Sửa khoá học lại ghi thẳng cột này lên —
     * kết quả là quản trị KHÔNG sửa nổi bất kỳ khoá học nào, kể cả chỉ đổi mỗi tiêu đề.
     * Thà tạm thời chưa lưu được ô "Bán khoá học này" còn hơn khoá cứng cả màn quản trị.
     *
     * Chạy migrate xong thì tự khớp lại, không phải sửa gì thêm.
     */
    public static function supportsProduct(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = Schema::hasColumn('courses', 'product_id');
        }

        return $has;
    }

    /** Đã mở bán trực tuyến chưa: phải có sản phẩm VÀ sản phẩm đó phải có giá học. */
    public function isPurchasable(): bool
    {
        return self::supportsProduct()
            && $this->product_id !== null
            && (int) ($this->product?->price ?? 0) > 0;
    }

    /** Giá học cá nhân của khoá, null khi chưa gắn sản phẩm. */
    public function learningPrice(): ?int
    {
        return (self::supportsProduct() && $this->product_id !== null)
            ? (int) ($this->product?->price ?? 0)
            : null;
    }

    /** Lớp đang mở của khoá này — dùng cho màn tự chọn lớp sau khi mua (C3). */
    public function openClassRooms(): HasMany
    {
        return $this->classRooms()->where('status', 'active');
    }

    /**
     * Các lộ trình có chứa khoá học này.
     *
     * Là nhiều–nhiều vì một khoá dùng lại được ở nhiều lộ trình — xem ghi chú ở migration
     * create_learning_path_course_table.
     */
    public function learningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_course')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /** Khoá này đã được xếp vào lộ trình nào chưa. */
    public function isInAnyLearningPath(): bool
    {
        return $this->learningPaths()->exists();
    }

    /**
     * Số buổi ĐÃ XẾP LỊCH THẬT của tất cả các lớp thuộc khoá này.
     *
     * Khác session_count (số buổi theo chương trình): con số này đếm class_sessions có ngày
     * giờ cụ thể. Màn quản trị hiện cả hai cạnh nhau để phát hiện lớp xếp thiếu buổi.
     */
    public function scheduledSessionCountFor(ClassRoom $classRoom): int
    {
        return $classRoom->sessions()->count();
    }
}
