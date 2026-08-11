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
    ];

    protected $casts = [
        'subjects' => 'array',
        'approval_status' => TeacherApprovalStatus::class,
        'approved_at' => 'datetime',
    ];

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
}
