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

    /**
     * Giá trị mặc định khi chưa có cấu hình hệ thống (fallback) — giá trị
     * thật do Super Admin quản lý qua system_settings (key
     * rating.min_reviews_to_rank), xem App\Services\SystemSettingService (18.8).
     */
    public const MIN_REVIEWS_TO_RANK = 5;

    public function isRankable(int $threshold = self::MIN_REVIEWS_TO_RANK): bool
    {
        return $this->review_count >= $threshold;
    }
}
