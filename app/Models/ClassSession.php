<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    protected $fillable = ['class_room_id', 'starts_at', 'ends_at', 'topic', 'location', 'summary'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Tài liệu/câu hỏi/đề thi/video/link gắn riêng cho buổi học này (note họp 13/8, mục 3). */
    public function sessionResources(): HasMany
    {
        return $this->hasMany(SessionResource::class);
    }

    /**
     * SỬA 9/9 (4) — các HOẠT ĐỘNG của buổi học, mỗi hoạt động gom nhiều tài nguyên và có công
     * tắc phát riêng cho học sinh (xem App\Models\SessionActivity).
     */
    public function activities(): HasMany
    {
        return $this->hasMany(SessionActivity::class)->orderBy('position')->orderBy('id');
    }
}
