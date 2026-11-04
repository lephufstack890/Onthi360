<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Con bấm "Xin thêm giờ chơi" trên máy → phụ huynh duyệt / từ chối. */
class AgentExtraTimeRequest extends Model
{
    protected $fillable = ['device_id', 'student_user_id', 'minutes', 'reason', 'status', 'for_date', 'decided_by', 'decided_at'];

    protected $casts = [
        'minutes' => 'integer',
        'for_date' => 'date',
        'decided_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(AgentDevice::class, 'device_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
