<?php

namespace App\Models;

use App\Enums\CompetitionOrganizerType;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $fillable = [
        'title', 'slug', 'type', 'organizer_type', 'organizer_name', 'assessment_id', 'rules',
        'starts_at', 'ends_at', 'publish_result_at', 'status', 'ranking_rule',
    ];

    protected $casts = [
        'type' => CompetitionType::class,
        'organizer_type' => CompetitionOrganizerType::class,
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

    /**
     * Giáo viên cố vấn/đồng hành — bắt buộc có ít nhất 1 người khi organizer_type=external
     * (note họp 13/8, mục 1: "cuộc thi ngoài đơn vị tổ chức thì cần có chuyên gia cố vấn
     * giáo viên đồng hành để tăng uy tín"), xem App\Services\Admin\CompetitionService.
     */
    public function advisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'competition_advisors', 'competition_id', 'teacher_id')->withTimestamps();
    }

    public function isExternallyOrganized(): bool
    {
        return $this->organizer_type === CompetitionOrganizerType::External;
    }

    /** "Chờ công bố" không lộ rank tạm thời nếu quy chế cấm (11.2). */
    public function ranksArePublic(): bool
    {
        return $this->status === CompetitionStatus::Published;
    }
}
