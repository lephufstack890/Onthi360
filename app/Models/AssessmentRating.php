<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SỬA 7/10 — một lượt chấm sao (1-5) của học sinh cho một đề luyện tập. Xem migration
 * add_difficulty_and_ratings_to_assessments.
 */
class AssessmentRating extends Model
{
    protected $fillable = ['assessment_id', 'user_id', 'rating'];

    protected $casts = ['rating' => 'integer'];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
