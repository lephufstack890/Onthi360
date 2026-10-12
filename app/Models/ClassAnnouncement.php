<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 12/10 — thông báo lớp học của giáo viên (bản nháp / đã đăng), theo TeacherNoticePanel của source mới.
 * Đăng xong thì gửi thông báo hệ thống tới học sinh (và phụ huynh đã liên kết nếu chọn) — xem
 * App\Services\Teacher\TeacherWorkspaceService::saveAnnouncement().
 */
class ClassAnnouncement extends Model
{
    public const STATE_DRAFT = 'draft';

    public const STATE_PUBLISHED = 'published';

    public const AUDIENCE_STUDENTS = 'students';

    public const AUDIENCE_FAMILIES = 'families';

    protected $fillable = [
        'teacher_id', 'class_room_id', 'audience', 'title', 'body', 'state',
        'student_count', 'parent_count', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'student_count' => 'integer',
        'parent_count' => 'integer',
    ];

    /** Bảng đã có trên máy chủ chưa (đẩy mã trước khi chạy migrate vẫn không vỡ trang). */
    public static function isReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('class_announcements');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function isPublished(): bool
    {
        return $this->state === self::STATE_PUBLISHED;
    }
}
