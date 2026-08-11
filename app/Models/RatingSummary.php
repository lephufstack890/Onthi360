<?php

namespace App\Models;

use App\Enums\ReviewTargetType;
use Illuminate\Database\Eloquent\Model;

class RatingSummary extends Model
{
    protected $fillable = [
        'target_type', 'target_id', 'avg_rating', 'review_count', 'distribution', 'updated_at_summary',
    ];

    protected $casts = [
        'target_type' => ReviewTargetType::class,
        'distribution' => 'array',
        'updated_at_summary' => 'datetime',
    ];

    /** Ngưỡng tối thiểu để hiển thị xếp hạng công khai (9.5). */
    public const MIN_REVIEWS_TO_RANK = 5;

    public function isRankable(): bool
    {
        return $this->review_count >= self::MIN_REVIEWS_TO_RANK;
    }
}
