<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $fillable = [
        'title', 'slug', 'type', 'assessment_id', 'rules', 'starts_at', 'ends_at',
        'publish_result_at', 'status', 'ranking_rule',
    ];

    protected $casts = [
        'type' => CompetitionType::class,
        'status' => CompetitionStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'publish_result_at' => 'datetime',
        'ranking_rule' => 'array',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function leaderboardEntries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /** "Chờ công bố" không lộ rank tạm thời nếu quy chế cấm (11.2). */
    public function ranksArePublic(): bool
    {
        return $this->status === CompetitionStatus::Published;
    }
}
