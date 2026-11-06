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
        'class_room_id', 'position', 'title', 'description', 'items', 'is_unlocked', 'unlocked_at', 'unlock_on', 'created_by',
    ];

    protected $casts = [
        'items' => 'array',
        'is_unlocked' => 'boolean',
        'unlocked_at' => 'datetime',
        'unlock_on' => 'date',
        'position' => 'integer',
    ];

    public static function isReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('class_progress_steps');
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }
}
