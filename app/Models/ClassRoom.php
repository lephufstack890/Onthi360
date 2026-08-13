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

    protected $fillable = ['course_id', 'code', 'name', 'schedule', 'status'];

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
