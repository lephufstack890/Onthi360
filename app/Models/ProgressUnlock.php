<?php

namespace App\Models;

use App\Enums\ProgressUnitType;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressUnlock extends Model
{
    use Auditable;

    /** Đọc bởi App\Concerns\Auditable nếu có set lý do trước save()/delete() (10.4, 16 mục 4). */
    public static ?string $auditReason = null;

    protected $fillable = ['class_room_id', 'unit_type', 'unit_id', 'opened_by', 'opened_at', 'closed_at'];

    protected $casts = [
        'unit_type' => ProgressUnitType::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
