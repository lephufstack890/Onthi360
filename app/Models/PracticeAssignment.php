<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SỬA 7/10 — một lượt giao bài/giao đề riêng cho một học sinh ở trang Luyện tập.
 * Xem migration create_practice_assignments_table.
 */
class PracticeAssignment extends Model
{
    public const TYPE_PROBLEM = 'problem';

    public const TYPE_EXAM = 'exam';

    protected $fillable = ['assigned_by', 'student_id', 'type', 'subject_id', 'deadline_at'];

    protected $casts = [
        'deadline_at' => 'datetime',
        'subject_id' => 'integer',
    ];

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
