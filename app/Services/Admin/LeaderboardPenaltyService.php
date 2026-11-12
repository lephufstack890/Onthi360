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

        $query = LeaderboardPenalty::query()->with(['user:id,name,email,phone,token_balance,avatar_path', 'creator:id,name'])
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

    /**
     * SỬA 12/11 — gợi ý cho ô chọn tài khoản (Select2): học sinh đang hoạt động, khớp tên / email / SĐT / ID.
     * Ô trống → 20 học sinh mới nhất.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchAccounts(string $q): array
    {
        $q = trim($q);
        $lockedIds = $this->ready()
            ? array_flip(LeaderboardPenalty::query()->currentlyLocked()->pluck('user_id')->map(fn ($id) => (int) $id)->all())
            : [];

        return User::query()
            ->whereNull('deleted_at')
            ->whereHas('roles', fn ($r) => $r->where('name', \App\Models\Role::STUDENT))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $w->where(function ($x) use ($q, $like) {
                    $x->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like);
                    if (preg_match('/^(?:VP-)?0*(\d+)$/i', $q, $m)) {
                        $x->orWhere('id', (int) $m[1]);
                    }
                });
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'province', 'avatar_path'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => (string) $u->name,
                // SỬA 12/11 (khách: "đổ tên, email và avatar thôi") — dòng phụ chỉ còn email (không có thì SĐT).
                'meta' => (string) ($u->email ?: $u->phone ?: ''),
                'initials' => self::initials((string) $u->name),
                'avatar' => self::avatar($u->avatar_path),
                'locked' => isset($lockedIds[$u->id]),
            ])->all();
    }

    /** SỬA 12/11 — chưa có ảnh đại diện thì dùng ảnh mặc định của giao diện (public/assets/user-avatar.png). */
    public static function avatar(?string $path): string
    {
        return \App\Services\Account\AvatarService::url($path) ?? asset('assets/user-avatar.png');
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, -2)))) ?: '?';
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
            throw ValidationException::withMessages(['account' => 'Không thể tự khóa chính mình.']);
        }
        if ($user->hasRole(\App\Models\Role::SUPER_ADMIN) && ! $admin->hasRole(\App\Models\Role::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['account' => 'Chỉ Super Admin mới được khóa tài khoản Super Admin.']);
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

            $hasHideAvatar = Schema::hasColumn('leaderboard_penalties', 'hide_avatar'); // SỬA 12/11 — chưa migrate thì bỏ qua cột này
            $penalty = LeaderboardPenalty::create(($hasHideAvatar ? ['hide_avatar' => (bool) ($data['hide_avatar'] ?? false)] : []) + [
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

            // SỬA 12/11 (khách: "khóa xong là không đăng nhập được nữa") — chuyển tài khoản sang Đã khóa (users.status = suspended):
            // AuthController chặn đăng nhập; phiên đang mở bị đăng xuất ngay (xoá bản ghi sessions).
            $wasStatus = (string) $fresh->status;
            $fresh->forceFill(['status' => 'suspended'])->save();
            $this->killSessions($fresh->id);

            $this->audit($admin, $penalty, 'locked', [
                'before' => ['status' => $wasStatus ?: 'active'],
                'after' => ['status' => 'suspended'],
                'penalty' => [
                    'lock' => $penalty->lockLabel(),
                    'unlock_at' => $penalty->unlock_at?->toIso8601String(),
                    'points_deducted' => $penalty->points_deducted, 'tokens_deducted' => $tokens,
                ],
            ], $penalty->reason.' · '.$penalty->lockLabel().(filled($data['note'] ?? null) ? ' — '.$data['note'] : ''));

            return $penalty;
        });

        LeaderboardService::forgetCache();

        return $penalty;
    }

    /** Khóa đang hiệu lực mới nhất của 1 tài khoản (null = không có). */
    public function activeLockFor(int $userId): ?LeaderboardPenalty
    {
        if (! $this->ready()) {
            return null;
        }
        self::releaseExpired($userId);

        return LeaderboardPenalty::query()->currentlyLocked()->where('user_id', $userId)->latest('locked_at')->first();
    }

    /**
     * Mở khóa tài khoản (nút "Mở khóa" ở danh sách người dùng). $refund = true → hủy luôn hình phạt (hoàn token, bỏ trừ điểm).
     * Tài khoản bị khóa tay (không có bản ghi xử phạt) cũng mở được.
     */
    public function unlockUser(User $admin, User $user, bool $refund, ?string $reason): void
    {
        $locks = $this->ready() ? LeaderboardPenalty::query()->currentlyLocked()->where('user_id', $user->id)->get() : collect();
        foreach ($locks as $lock) {
            $refund ? $this->revoke($admin, $lock, $reason ?: 'Hủy khi mở khóa') : $this->unlock($admin, $lock, false);
        }
        if ($user->status === 'suspended') {
            $user->forceFill(['status' => 'active'])->save();
        }
        try {
            AuditLog::create([
                'actor_id' => $admin->id, 'action' => 'unlocked', 'auditable_type' => User::class, 'auditable_id' => $user->id,
                'changes' => ['before' => ['status' => 'suspended'], 'after' => ['status' => 'active']],
                'reason' => $reason ?: ($refund ? 'Mở khóa và hủy hình phạt (hoàn token, bỏ trừ điểm)' : 'Mở khóa sớm'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
        LeaderboardService::forgetCache();
    }

    /**
     * Tự mở khóa các tài khoản đã HẾT HẠN khóa (gọi lúc đăng nhập, khi mở danh sách người dùng, trang bảng xếp hạng).
     * Chỉ mở tài khoản đang 'suspended' mà KHÔNG còn khóa nào hiệu lực; đánh dấu unlocked_at = unlock_at để lần sau admin
     * khóa tay thì không bị mở nhầm.
     */
    public static function releaseExpired(?int $userId = null): void
    {
        try {
            if (! Schema::hasTable('leaderboard_penalties')) {
                return;
            }
            $now = Carbon::now();
            $expired = LeaderboardPenalty::query()
                ->whereNull('revoked_at')->whereNull('unlocked_at')
                ->whereNotNull('unlock_at')->where('unlock_at', '<=', $now)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->limit(500)->get();
            if ($expired->isEmpty()) {
                return;
            }
            foreach ($expired as $p) {
                $p->forceFill(['unlocked_at' => $p->unlock_at])->save();
            }
            foreach ($expired->pluck('user_id')->unique() as $uid) {
                $stillLocked = LeaderboardPenalty::query()->currentlyLocked($now)->where('user_id', $uid)->exists();
                if (! $stillLocked) {
                    User::query()->whereKey($uid)->where('status', 'suspended')->update(['status' => 'active']);
                }
            }
            LeaderboardService::forgetCache();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function killSessions(int $userId): void
    {
        try {
            if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $userId)->delete();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Mở khóa sớm: tài khoản trở lại bảng xếp hạng; điểm/token đã trừ giữ nguyên. */
    public function unlock(User $admin, LeaderboardPenalty $penalty, bool $reactivate = true): void
    {
        if (! $penalty->isLocked()) {
            return;
        }
        $penalty->update(['unlocked_at' => Carbon::now()]);
        $this->audit($admin, $penalty, 'leaderboard_unlocked', []);
        if ($reactivate) {
            $this->reactivateIfFree($penalty->user_id);
        }
        LeaderboardService::forgetCache();
    }

    private function reactivateIfFree(int $userId): void
    {
        if (! LeaderboardPenalty::query()->currentlyLocked()->where('user_id', $userId)->exists()) {
            User::query()->whereKey($userId)->where('status', 'suspended')->update(['status' => 'active']);
        }
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
            $this->reactivateIfFree($penalty->user_id);
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
