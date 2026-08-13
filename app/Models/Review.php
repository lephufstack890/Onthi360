<?php

namespace App\Models;

use App\Enums\ReviewerRole;
use App\Enums\ReviewStatus;
use App\Enums\ReviewTargetType;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use Auditable;

    /** Đọc bởi App\Concerns\Auditable nếu có set lý do trước save()/delete() (10.4, 16 mục 4). */
    public static ?string $auditReason = null;

    protected $fillable = [
        'reviewer_id', 'reviewer_role', 'target_type', 'target_id', 'target_version',
        'overall_rating', 'criteria_scores', 'comment', 'disclosure_ack', 'status',
        'moderation_reason', 'published_at', 'editable_until',
        'admin_reply', 'admin_reply_by', 'admin_reply_at',
    ];

    protected $casts = [
        'reviewer_role' => ReviewerRole::class,
        'target_type' => ReviewTargetType::class,
        'criteria_scores' => 'array',
        'disclosure_ack' => 'boolean',
        'published_at' => 'datetime',
        'editable_until' => 'datetime',
        'admin_reply_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** 9.4: chỉ Admin đăng phản hồi chính thức, chỉ sau khi review đã "Đã công bố". */
    public function adminReplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_reply_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }

    public function isEditable(): bool
    {
        return $this->editable_until !== null && $this->editable_until->isFuture();
    }

    public function isPublished(): bool
    {
        return $this->status === ReviewStatus::Published;
    }
}
