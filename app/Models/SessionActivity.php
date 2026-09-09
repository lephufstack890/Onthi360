<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SỬA 9/9 (4) — 1 HOẠT ĐỘNG trong buổi học (khách: "trong hoạt động thì có nhiều tài nguyên buổi
 * học... giáo viên phải click icon play thì học sinh mới thấy được").
 *
 * Quy ước DUY NHẤT về việc học sinh thấy hay không: published_at.
 *   NULL      -> đang soạn, CHỈ giáo viên thấy
 *   có giá trị -> đã phát, học sinh thấy hoạt động này cùng toàn bộ tài nguyên bên trong
 * Mọi nơi hiển thị cho học sinh PHẢI lọc qua scopePublished() để không có chỗ nào lộ nhầm.
 */
class SessionActivity extends Model
{
    protected $fillable = ['class_session_id', 'title', 'note', 'position', 'published_at', 'created_by'];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    /** Tài nguyên thuộc hoạt động này — xoá hoạt động là xoá theo (cascade ở migration). */
    public function resources(): HasMany
    {
        return $this->hasMany(SessionResource::class, 'activity_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Lọc hoạt động ĐÃ PHÁT — dùng ở mọi truy vấn phục vụ học sinh/phụ huynh. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }
}
