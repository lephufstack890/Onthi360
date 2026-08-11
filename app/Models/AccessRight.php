<?php

namespace App\Models;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRight extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id', 'product_id', 'scope', 'starts_at', 'expires_at', 'status',
        'class_limit', 'source', 'source_id', 'created_by',
    ];

    protected $casts = [
        'scope' => AccessScope::class,
        'status' => AccessRightStatus::class,
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Còn hiệu lực TẠI thời điểm hiện tại theo giờ máy chủ — không tin client (16 mục 3). */
    public function isCurrentlyActive(): bool
    {
        return $this->status === AccessRightStatus::Active
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /** Quyền dạy không có giới hạn số lớp (5.3, 7.2): class_limit phải là null. */
    public function isUnlimitedTeaching(): bool
    {
        return $this->scope === AccessScope::TeacherTeaching && $this->class_limit === null;
    }
}
