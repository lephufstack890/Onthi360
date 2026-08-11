<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'class_room_id', 'assessment_id', 'opens_at', 'closes_at', 'due_at',
        'rules', 'instructions', 'status', 'created_by',
    ];

    protected $casts = [
        'status' => AssignmentStatus::class,
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'due_at' => 'datetime',
        'rules' => 'array',
    ];

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function isOpenNow(): bool
    {
        $now = now();

        return $this->status === AssignmentStatus::Open
            && ($this->opens_at === null || $this->opens_at->lte($now))
            && ($this->closes_at === null || $this->closes_at->gte($now));
    }
}
