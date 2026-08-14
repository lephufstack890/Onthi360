<?php

namespace App\Models;

use App\Enums\SessionResourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionResource extends Model
{
    protected $fillable = [
        'class_session_id', 'type', 'material_id', 'question_id', 'assessment_id',
        'title', 'url', 'note', 'added_by',
    ];

    protected $casts = [
        'type' => SessionResourceType::class,
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** Tên hiển thị: tài liệu/câu hỏi/đề thi lấy tên thật (theo bản ghi hiện tại); video/link/note dùng title tự nhập. */
    public function displayTitle(): string
    {
        return match ($this->type) {
            SessionResourceType::Material => $this->material?->title ?? '(học liệu đã gỡ)',
            SessionResourceType::Question => $this->question?->title ?? '(câu hỏi đã gỡ)',
            SessionResourceType::Assessment => $this->assessment?->title ?? '(đề đã gỡ)',
            default => $this->title ?? '',
        };
    }
}
