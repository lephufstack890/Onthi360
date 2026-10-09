<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentItem extends Model
{
    protected $fillable = ['assessment_id', 'question_id', 'order', 'points_override'];

    // SỬA 11/10 — điểm từng câu do người ra đề nhập, có thể là số thập phân.
    protected $casts = ['points_override' => 'float'];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function effectivePoints(): float
    {
        return (float) ($this->points_override ?? $this->question->points);
    }
}
