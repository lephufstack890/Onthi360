<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassRoom extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = [
        'course_id', 'code', 'name', 'schedule', 'status',
        // SỬA 16/9 — 4 trường mô tả lớp cho thẻ lớp ngoài trang công khai, xem migration
        // add_display_fields_to_class_rooms_table.
        'location', 'address', 'format', 'capacity',
    ];

    /**
     * Máy chủ đã chạy migration thêm 4 cột mô tả lớp chưa.
     *
     * Cùng cách phòng vệ như Course::supportsProduct(): đã có lần đẩy mã lên VPS mà quên chạy
     * `php artisan migrate`, thế là cả màn quản trị hỏng, không sửa nổi mỗi cái tên lớp. Hỏi
     * lược đồ MỘT LẦN mỗi vòng đời tiến trình rồi nhớ lại.
     */
    public static function supportsDisplayFields(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = \Illuminate\Support\Facades\Schema::hasColumn('class_rooms', 'location');
        }

        return $has;
    }

    protected $casts = [
        'schedule' => 'array',
    ];

    /** Đọc bởi App\Concerns\Auditable — lý do xóa mềm lớp (10.4), xem CourseService::destroyClass(). */
    public static ?string $auditReason = null;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_teachers')->withPivot('role')->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_enrollments', 'class_room_id', 'student_id')
            ->wherePivot('status', 'active')
            ->withPivot(['status', 'enrolled_at', 'left_at'])
            ->withTimestamps();
    }

    public function classMaterials(): HasMany
    {
        return $this->hasMany(ClassMaterial::class);
    }

    public function progressUnlocks(): HasMany
    {
        return $this->hasMany(ProgressUnlock::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** Giáo viên đang phụ trách (main hoặc co_teacher) — dùng cho check quyền 7.2. */
    public function isTaughtBy(User $user): bool
    {
        return $this->teachers()->where('users.id', $user->id)->exists();
    }
}
