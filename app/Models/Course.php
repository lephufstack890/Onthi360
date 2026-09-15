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

class Course extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'description', 'cover_image_path', 'subject', 'grade', 'status', 'created_by',
        // SỬA 15/9 — bốn trường "bậc" khi khoá học nằm trong một lộ trình, xem migration
        // add_level_fields_to_courses_table.
        'level_code', 'level_subtitle', 'outcome', 'session_count',
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
