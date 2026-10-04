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
        'is_featured', 'is_expert', 'achievement_note',
        // SỬA 4/10 — 4 trường cho trang vinh danh, xem migration add_showcase_fields.
        'display_name', 'workplace', 'role_title', 'display_rating',
    ];

    protected $casts = [
        'subjects' => 'array',
        'approval_status' => TeacherApprovalStatus::class,
        'approved_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_expert' => 'boolean',
        'display_rating' => 'float',
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

    /**
     * SỬA 4/10 — chuyên gia CHỈ tính khi đã được vinh danh và đã duyệt hồ sơ, giống isFeatured():
     * huy hiệu "Chuyên gia" là lời khẳng định công khai, không được hiện ra từ một hồ sơ chưa
     * qua duyệt chỉ vì cột is_expert lỡ bật.
     */
    /** Tên bày ra trang vinh danh — admin đặt riêng được, không đụng tên tài khoản. */
    public function showcaseName(): string
    {
        // ?-> chứ không phải ->: hồ sơ trưng bày không gắn tài khoản nào (user_id NULL), xem
        // migration allow_standalone_teacher_profiles.
        return filled($this->display_name) ? $this->display_name : (string) ($this->user?->name ?? '');
    }

    public function isExpert(): bool
    {
        return $this->is_expert && $this->isFeatured();
    }
}
