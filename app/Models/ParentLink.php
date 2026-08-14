<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ParentLinkStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentLink extends Model
{
    use Auditable;

    protected $fillable = [
        'parent_user_id', 'student_user_id', 'status', 'verification_method', 'verified_at',
    ];

    protected $casts = [
        'status' => ParentLinkStatus::class,
        'verified_at' => 'datetime',
    ];

    /**
     * Đọc bởi App\Concerns\Auditable — set tạm trước khi update() khi cần ghi lý do (admin
     * từ chối/thu hồi liên kết, 16 mục 4), rồi trả về null ngay sau đó (xem
     * App\Services\Admin\UserService::rejectParentLink()).
     */
    public static ?string $auditReason = null;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function isVerified(): bool
    {
        return $this->status === ParentLinkStatus::Verified;
    }
}
