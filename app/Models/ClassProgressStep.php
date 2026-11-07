<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 6/11 — một bước trong "Tiến trình dạy học" của lớp (tab 6 không gian lớp). items = danh sách tài liệu / bài tập của
 * lớp gắn vào bước: [{type: material|assignment, id}] (id = class_materials.id hoặc assignments.id).
 */
class ClassProgressStep extends Model
{
    protected $fillable = [
        'class_room_id', 'position', 'title', 'description', 'items', 'is_unlocked', 'unlocked_at', 'unlock_on', 'unlock_at', 'created_by',
    ];

    protected $casts = [
        'items' => 'array',
        'is_unlocked' => 'boolean',
        'unlocked_at' => 'datetime',
        'unlock_on' => 'date',
        'unlock_at' => 'datetime', // SỬA 7/11 — hẹn NGÀY + GIỜ PHÚT tự mở khoá (migration 001430)
        'position' => 'integer',
    ];

    public static function isReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('class_progress_steps');
    }

    /** SỬA 7/11 — đã chạy migration 001430 (cột unlock_at) chưa; chưa thì vẫn chạy theo unlock_on (chỉ ngày) như cũ. */
    public static function supportsUnlockAt(): bool
    {
        static $has = null;

        return $has ??= self::isReady() && Schema::hasColumn('class_progress_steps', 'unlock_at');
    }

    /** Thời điểm hẹn tự mở: unlock_at; dữ liệu cũ chỉ có ngày (unlock_on) thì tính 00:00 ngày đó. */
    public function scheduledAt(): ?\Illuminate\Support\Carbon
    {
        if (self::supportsUnlockAt() && $this->unlock_at !== null) {
            return $this->unlock_at;
        }

        return $this->unlock_on?->copy()->startOfDay();
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }
}
