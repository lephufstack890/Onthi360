<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Luật Chocon Desktop phụ huynh đặt cho một con (áp dụng cho mọi máy của con). */
class AgentPolicy extends Model
{
    protected $fillable = [
        'student_user_id', 'updated_by', 'daily_game_minutes',
        'study_only_mode', 'study_only_in_study_hours', 'study_hours_start', 'study_hours_end',
        'sleep_start', 'sleep_end', 'allowed_domains', 'blocked_domains', 'blocked_processes',
        'disable_private_browsing', 'game_requires_study', 'study_goal_items',
        'bonus_minutes_per_item', 'bonus_cap_minutes', 'lock_now', 'lock_message', 'parent_pin_hash',
    ];

    protected $casts = [
        'daily_game_minutes' => 'integer',
        'study_only_mode' => 'boolean',
        'study_only_in_study_hours' => 'boolean',
        'allowed_domains' => 'array',
        'blocked_domains' => 'array',
        'blocked_processes' => 'array',
        'disable_private_browsing' => 'boolean',
        'game_requires_study' => 'boolean',
        'study_goal_items' => 'integer',
        'bonus_minutes_per_item' => 'integer',
        'bonus_cap_minutes' => 'integer',
        'lock_now' => 'boolean',
    ];

    protected $hidden = ['parent_pin_hash'];

    /** Giá trị mặc định khi phụ huynh chưa cài gì: chưa giới hạn, chỉ tắt ẩn danh. */
    public static function forStudent(int $studentId): self
    {
        return self::query()->firstOrNew(['student_user_id' => $studentId], [
            'study_only_in_study_hours' => true,
            'disable_private_browsing' => true,
            'study_goal_items' => 3,
            'bonus_minutes_per_item' => 0,
            'bonus_cap_minutes' => 60,
        ]);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
