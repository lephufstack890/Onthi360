<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\LeaderboardPenalty;
use App\Models\User;
use App\Services\Public\LeaderboardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 12/11 — Quản trị › Bảng xếp hạng › Tài khoản vi phạm: xử phạt / mở khóa sớm / hủy hình phạt.
 * Xử phạt xong tài khoản hiện ngay ở tab "Tài khoản vi phạm" của trang Bảng xếp hạng công khai.
 */
class LeaderboardPenaltyService
{
    public function __construct(private LeaderboardService $leaderboard) {}

    public function ready(): bool
    {
        try {
            return Schema::hasTable('leaderboard_penalties');
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Số tài khoản đang bị khóa — viên số trên tab admin. */
    public function lockedCount(): int
    {
        return $this->ready() ? LeaderboardPenalty::query()->currentlyLocked()->count() : 0;
    }

    /** @return array<string, mixed> */
    public function indexData(?string $filter): array
    {
        $filter = in_array($filter, ['locked', 'all'], true) ? $filter : 'locked';

        if (! $this->ready()) {
            return ['penalties' => collect(), 'filter' => $filter, 'ready' => false, 'counts' => ['locked' => 0, 'all' => 0, 'tokens' => 0]];
        }

        $query = LeaderboardPenalty::query()->with(['user:id,name,email,phone,token_balance', 'creator:id,name'])
            ->orderByDesc('locked_at')->orderByDesc('id');
        if ($filter === 'locked') {
            $query->currentlyLocked();
        }

        return [
            'penalties' => $query->limit(300)->get(),
            'filter' => $filter,
            'ready' => true,
            'counts' => [
                'locked' => LeaderboardPenalty::query()->currentlyLocked()->count(),
                'all' => LeaderboardPenalty::query()->count(),
                'tokens' => (int) LeaderboardPenalty::query()->effective()->sum('tokens_deducted'),
            ],
        ];
    }

    /** Tìm tài khoản theo ID, email, số điện thoại (hoặc "VP-000123"). */
    public function findAccount(string $needle): ?User
    {
        $needle = trim($needle);
        if (preg_match('/^(?:VP-)?0*(\d+)$/i', $needle, $m)) {
            $byId = User::query()->find((int) $m[1]);
            if ($byId) {
                return $byId;
            }
        }

        return User::query()->where('email', $needle)->orWhere('phone', $needle)->first();
    }

    /** @param  array{account: string, reason: string, note?: ?string, lock_days: int, points_deducted?: ?float, tokens_deducted?: ?int, hide_name?: bool}  $data */
    public function store(User $admin, array $data): LeaderboardPenalty
    {
        $user = $this->findAccount($data['account']);
        if ($user === null) {
            throw ValidationException::withMessages(['account' => 'Không tìm thấy tài khoản này (nhập ID, email hoặc số điện thoại).']);
        }
        if ($user->id === $admin->id) {
            throw ValidationException::withMessages(['account' => 'Không thể tự xử phạt chính mình.']);
        }

        $standing = $this->leaderboard->standingFor($user->id);
        $days = (int) $data['lock_days'];
        $now = Carbon::now();

        $penalty = DB::transaction(function () use ($admin, $user, $data, $standing, $days, $now) {
            // Trừ token: không để ví âm — trừ tối đa số đang có, ghi lại số thực trừ.
            $fresh = User::query()->lockForUpdate()->find($user->id);
            $tokens = min(max(0, (int) ($data['tokens_deducted'] ?? 0)), max(0, (int) $fresh->token_balance));
            if ($tokens > 0) {
                $fresh->decrement('token_balance', $tokens);
            }

            $penalty = LeaderboardPenalty::create([
                'user_id' => $user->id,
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
                'lock_days' => $days > 0 ? $days : null,
                'points_deducted' => max(0, (float) ($data['points_deducted'] ?? 0)),
                'tokens_deducted' => $tokens,
                'score_at_lock' => $standing['score'],
                'badge_at_lock' => $standing['badge'],
                'hide_name' => (bool) ($data['hide_name'] ?? false),
                'locked_at' => $now,
                'unlock_at' => $days > 0 ? $now->copy()->addDays($days) : null,
                'created_by' => $admin->id,
            ]);

            $this->audit($admin, $penalty, 'leaderboard_penalized', [
                'after' => [
                    'reason' => $penalty->reason, 'lock' => $penalty->lockLabel(),
                    'points_deducted' => $penalty->points_deducted, 'tokens_deducted' => $tokens,
                ],
            ], $data['note'] ?? null);

            return $penalty;
        });

        LeaderboardService::forgetCache();

        return $penalty;
    }

    /** Mở khóa sớm: tài khoản trở lại bảng xếp hạng; điểm/token đã trừ giữ nguyên. */
    public function unlock(User $admin, LeaderboardPenalty $penalty): void
    {
        if (! $penalty->isLocked()) {
            return;
        }
        $penalty->update(['unlocked_at' => Carbon::now()]);
        $this->audit($admin, $penalty, 'leaderboard_unlocked', []);
        LeaderboardService::forgetCache();
    }

    /** Hủy hình phạt (xử nhầm): hoàn token đã trừ, bỏ trừ điểm, gỡ khỏi danh sách vi phạm. */
    public function revoke(User $admin, LeaderboardPenalty $penalty, string $reason): void
    {
        if ($penalty->revoked_at !== null) {
            return;
        }

        DB::transaction(function () use ($admin, $penalty, $reason) {
            if ($penalty->tokens_deducted > 0 && $penalty->user) {
                $penalty->user->increment('token_balance', $penalty->tokens_deducted);
            }
            $penalty->update(['revoked_at' => Carbon::now(), 'revoke_reason' => $reason]);
            $this->audit($admin, $penalty, 'leaderboard_penalty_revoked', ['refund_tokens' => $penalty->tokens_deducted], $reason);
        });

        LeaderboardService::forgetCache();
    }

    private function audit(User $admin, LeaderboardPenalty $penalty, string $action, array $changes, ?string $reason = null): void
    {
        try {
            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => $action,
                'auditable_type' => User::class,
                'auditable_id' => $penalty->user_id,
                'changes' => $changes + ['penalty_id' => $penalty->id],
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            report($e); // nhật ký lỗi không được làm hỏng thao tác xử phạt
        }
    }
}
