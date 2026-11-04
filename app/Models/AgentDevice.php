<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Máy tính của con đã ghép với tài khoản phụ huynh (Chocon Desktop). */
class AgentDevice extends Model
{
    protected $fillable = [
        'parent_user_id', 'student_user_id', 'name', 'machine_id', 'token_hash', 'agent_version', 'os_version',
        'policy_version', 'tray_connected', 'enforcement_enabled', 'locked', 'last_seen_at', 'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'tray_connected' => 'boolean',
        'enforcement_enabled' => 'boolean',
        'locked' => 'boolean',
        'policy_version' => 'integer',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes((int) config('chocon.offline_after_minutes', 5)));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
