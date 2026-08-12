<?php

namespace App\Models;

use App\Enums\TeacherApprovalStatus;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id', 'bio', 'subjects', 'approval_status',
        'approved_by', 'approved_at', 'rejection_reason',
        'is_featured', 'achievement_note',
    ];

    protected $casts = [
        'subjects' => 'array',
        'approval_status' => TeacherApprovalStatus::class,
        'approved_at' => 'datetime',
        'is_featured' => 'boolean',
    ];

    /**
     * Đọc bởi App\Concerns\Auditable — set tạm trước khi update() khi cần ghi lý do
     * (từ chối/tạm dừng, 16 mục 4), rồi trả về null ngay sau đó (xem
     * App\Services\Admin\TeacherApprovalService).
     */
    public static ?string $auditReason = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === TeacherApprovalStatus::Approved;
    }

    public function isFeatured(): bool
    {
        return $this->is_featured && $this->isApproved();
    }
}
