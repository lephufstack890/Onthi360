<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** Câu nhận xét mặc định khi giáo viên chưa tự nhập (note họp 13/8: "mặc định là một
     * câu nào đó") — vẫn sửa được tự do trước khi lưu. */
    public const DEFAULT_NOTE = 'Học tập tích cực, tiếp thu tốt trong buổi học.';

    protected $fillable = [
        'class_session_id', 'student_id', 'status', 'source', 'note', 'needs_more_practice', 'recorded_by',
    ];

    protected $casts = [
        'status' => AttendanceStatus::class,
        'source' => AttendanceSource::class,
        'needs_more_practice' => 'boolean',
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isAuto(): bool
    {
        return $this->source === AttendanceSource::Auto;
    }
}
