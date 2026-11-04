<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Một phiên dùng máy gửi lên từ Chocon Desktop. Khóa chính là UUID máy sinh ra (gửi lại không bị trùng). */
class AgentActivitySession extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'device_id', 'student_user_id', 'started_at', 'ended_at', 'duration_seconds',
        'process_name', 'window_title', 'domain', 'category',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];
}
