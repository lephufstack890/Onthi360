<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SỬA 12/11 — một lần xử phạt tài khoản trên bảng xếp hạng (tab "Tài khoản vi phạm").
 * Xem database/migrations/2025_01_01_001450_create_leaderboard_penalties_table.php.
 */
class LeaderboardPenalty extends Model
{
    /** Lý do gợi ý ở form admin (vẫn cho nhập lý do khác). */
    public const REASONS = [
        'Sử dụng nhiều tài khoản trong một lượt thi',
        'Chia sẻ đáp án trong lượt thi',
        'Sao chép bài làm trong lượt đánh giá',
        'Tự động gửi bài liên tục',
        'Đăng nội dung không phù hợp',
        'Gian lận điểm / khai thác lỗi hệ thống',
    ];

    /** Số ngày khóa gợi ý; 0 = vĩnh viễn. */
    public const LOCK_DAYS = [1, 3, 7, 14, 30, 90, 0];

    protected $fillable = [
        'user_id', 'reason', 'note', 'lock_days', 'points_deducted', 'tokens_deducted', 'score_at_lock', 'badge_at_lock',
        'hide_name', 'locked_at', 'unlock_at', 'unlocked_at', 'revoked_at', 'revoke_reason', 'created_by',
    ];

    protected $casts = [
        'lock_days' => 'integer',
        'points_deducted' => 'float',
        'tokens_deducted' => 'integer',
        'score_at_lock' => 'float',
        'hide_name' => 'boolean',
        'locked_at' => 'datetime',
        'unlock_at' => 'datetime',
        'unlocked_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Hình phạt còn hiệu lực (chưa bị hủy) — mức trừ điểm vẫn tính. */
    public function scopeEffective(Builder $q): Builder
    {
        return $q->whereNull('revoked_at');
    }

    /** Đang khóa ngay lúc này: chưa hủy, chưa mở khóa sớm, chưa tới hạn mở khóa (hoặc vĩnh viễn). */
    public function scopeCurrentlyLocked(Builder $q, ?\DateTimeInterface $at = null): Builder
    {
        // SỬA 12/11 — nhận cả Carbon\Carbon (LeaderboardService) lẫn Illuminate\Support\Carbon, tránh TypeError.
        $at = $at === null ? Carbon::now() : Carbon::instance($at);

        return $q->whereNull('revoked_at')
            ->whereNull('unlocked_at')
            ->where('locked_at', '<=', $at)
            ->where(fn ($w) => $w->whereNull('unlock_at')->orWhere('unlock_at', '>', $at));
    }

    public function isLocked(): bool
    {
        if ($this->revoked_at !== null || $this->unlocked_at !== null) {
            return false;
        }

        return $this->unlock_at === null || $this->unlock_at->isFuture();
    }

    public function lockLabel(): string
    {
        return $this->lock_days ? 'Khóa '.$this->lock_days.' ngày' : 'Khóa vĩnh viễn';
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'Đã hủy',
            $this->unlocked_at !== null => 'Đã mở khóa sớm',
            $this->isLocked() => 'Bị khóa',
            default => 'Hết hạn khóa',
        };
    }

    /** Mã tài khoản hiển thị khi ẩn tên: VP-000123. */
    public function accountCode(): string
    {
        return 'VP-'.str_pad((string) $this->user_id, 6, '0', STR_PAD_LEFT);
    }
}
