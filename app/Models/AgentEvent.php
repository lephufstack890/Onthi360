<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sự kiện máy con gửi lên (đóng game giờ cấm, thử gỡ phần mềm, chỉnh giờ...). */
class AgentEvent extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'device_id', 'student_user_id', 'type', 'occurred_at', 'data'];

    protected $casts = [
        'occurred_at' => 'datetime',
        'data' => 'array',
    ];
}
