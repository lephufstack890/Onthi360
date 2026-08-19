<?php

namespace App\Models;

use App\Enums\AttemptSource;
use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    protected $fillable = [
        'user_id', 'assessment_id', 'assignment_id', 'class_room_id', 'source',
        'started_at', 'submitted_at', 'status', 'total_score', 'is_provisional',
    ];

    protected $casts = [
        'source' => AttemptSource::class,
        'status' => AttemptStatus::class,
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'is_provisional' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    /** Kết quả tổng là "tạm tính" tới khi mọi câu cần chấm hoàn tất (6.3). */
    public function recalculateProvisionalFlag(): void
    {
        $this->is_provisional = $this->answers()
            ->whereIn('verdict', ['pending', 'queued', 'judging'])
            ->exists();
    }
}
