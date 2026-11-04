<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/11 — thông báo chung do Quản trị đăng (mục "Thông báo" khu quản trị). Đăng xong gửi thông báo hệ thống
 * (kênh database, cùng bảng notifications) tới học sinh / phụ huynh / giáo viên được chọn — xem
 * App\Services\Admin\SystemAnnouncementService. Thông báo lớp của giáo viên vẫn là App\Models\ClassAnnouncement.
 */
class SystemAnnouncement extends Model
{
    public const STATE_DRAFT = 'draft';

    public const STATE_PUBLISHED = 'published';

    public const STATE_RECALLED = 'recalled';

    public const AUDIENCES = [
        'students' => 'Học sinh',
        'parents' => 'Phụ huynh',
        'teachers' => 'Giáo viên',
    ];

    public const LEVELS = [
        'normal' => 'Thông thường',
        'important' => 'Quan trọng',
    ];

    protected $fillable = [
        'author_id', 'title', 'body', 'level', 'audiences', 'scope', 'class_ids', 'pin_home', 'pin_until', 'state',
        'student_count', 'parent_count', 'teacher_count', 'published_at', 'recalled_at',
    ];

    protected $casts = [
        'audiences' => 'array',
        'class_ids' => 'array',
        'pin_home' => 'boolean',
        'pin_until' => 'datetime',
        'published_at' => 'datetime',
        'recalled_at' => 'datetime',
        'student_count' => 'integer',
        'parent_count' => 'integer',
        'teacher_count' => 'integer',
    ];

    /** Bảng đã có trên máy chủ chưa (đẩy mã trước khi chạy migrate vẫn không vỡ trang). */
    public static function isReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('system_announcements');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isPublished(): bool
    {
        return $this->state === self::STATE_PUBLISHED;
    }

    /** Khoá gắn vào dữ liệu thông báo đã gửi — dùng để mở trang chi tiết và để thu hồi. */
    public function refKey(): string
    {
        return 'sys:'.$this->id;
    }

    /** @return list<string> */
    public function audienceList(): array
    {
        return array_values(array_intersect(array_keys(self::AUDIENCES), (array) ($this->audiences ?? [])));
    }

    public function audienceLabel(): string
    {
        $labels = array_map(fn ($a) => self::AUDIENCES[$a], $this->audienceList());

        return $labels === [] ? 'Chưa chọn người nhận' : implode(', ', $labels);
    }

    /** Đang ghim ở trang chủ (đã đăng, bật ghim, chưa quá hạn ghim). */
    public function isPinnedNow(): bool
    {
        return $this->isPublished() && $this->pin_home && ($this->pin_until === null || $this->pin_until->isFuture());
    }
}
