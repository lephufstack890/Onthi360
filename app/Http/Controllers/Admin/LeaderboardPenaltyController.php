<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaderboardPenalty;
use App\Services\Admin\LeaderboardPenaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** SỬA 12/11 — Quản trị › Bảng xếp hạng › Tài khoản vi phạm. */
class LeaderboardPenaltyController extends Controller
{
    public function __construct(private LeaderboardPenaltyService $penalties) {}

    public function index(Request $request): View|RedirectResponse
    {
        // SỬA 12/11 — màn riêng đã gộp sang Người dùng.
        if (! $request->boolean('legacy')) {
            return redirect()->route('admin.users.index');
        }

        return view('admin.ranking.violations', $this->penalties->indexData($request->query('filter')) + [
            'reasons' => LeaderboardPenalty::REASONS,
            'lockDays' => LeaderboardPenalty::LOCK_DAYS,
        ]);
    }

    /** SỬA 12/11 — ô chọn tài khoản kiểu Select2: tìm theo tên / email / SĐT / ID, trả JSON. */
    public function accounts(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(['items' => $this->penalties->searchAccounts((string) $request->query('q', ''))]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->penalties->ready(), 503, 'Chưa chạy migration bảng leaderboard_penalties (php artisan migrate).');

        $data = $request->validate([
            'account' => ['required', 'string', 'max:190'],
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lock_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'points_deducted' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'tokens_deducted' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'hide_name' => ['nullable', 'boolean'],
            'hide_avatar' => ['nullable', 'boolean'],
        ], [], [
            'account' => 'tài khoản', 'reason' => 'lý do', 'lock_days' => 'số ngày khóa',
            'points_deducted' => 'điểm trừ', 'tokens_deducted' => 'token trừ',
        ]);

        $penalty = $this->penalties->store(Auth::user(), $data);

        return redirect()->route('admin.ranking.violations.index')
            ->with('status', 'penalty-created')
            ->with('penaltyTokens', $penalty->tokens_deducted);
    }

    /**
     * SỬA 12/11 (khách: "gộp sang màn Người dùng — nút Khóa ở từng dòng, bấm thì hiện popup xử lý") — admin.users.lock.
     * Khóa = không đăng nhập được + gỡ khỏi bảng xếp hạng + hiện ở tab công khai "Tài khoản vi phạm".
     */
    public function lockUser(Request $request, \App\Models\User $user): RedirectResponse
    {
        abort_unless($this->penalties->ready(), 503, 'Chưa chạy migration bảng leaderboard_penalties (php artisan migrate).');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lock_days' => ['required', 'integer', 'min:0', 'max:3650'],
            // SỬA 12/11 — trừ theo % (0–100).
            'points_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tokens_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hide_name' => ['nullable', 'boolean'],
            'hide_avatar' => ['nullable', 'boolean'],
        ], ['reason.required' => 'Hãy chọn hoặc nhập lý do khóa.'], [
            'reason' => 'lý do', 'lock_days' => 'thời gian khóa', 'points_percent' => '% điểm trừ', 'tokens_percent' => '% token trừ',
        ]);
        $data['points_percent'] = (float) ($data['points_percent'] ?? 0);
        $data['tokens_percent'] = (float) ($data['tokens_percent'] ?? 0);

        try {
            $this->penalties->store(Auth::user(), $data + ['account' => (string) $user->id]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors(['lock' => collect($e->errors())->flatten()->first()])->withInput()->with('lockUser', $user->id);
        }

        return back()->with('status', 'user-locked')->with('lockedName', $user->name);
    }

    /** admin.users.unlock — mở khóa; mode=refund thì hủy luôn hình phạt (hoàn token, bỏ trừ điểm). */
    public function unlockUser(Request $request, \App\Models\User $user): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['nullable', 'string', 'in:keep,refund'],
            'reason' => [$request->input('mode') === 'refund' ? 'required' : 'nullable', 'string', 'max:500'],
        ], ['reason.required' => 'Hủy hình phạt cần ghi lý do.']);

        $this->penalties->unlockUser(Auth::user(), $user, ($data['mode'] ?? 'keep') === 'refund', $data['reason'] ?? null);

        return back()->with('status', 'user-unlocked')->with('lockedName', $user->name);
    }

    public function unlock(LeaderboardPenalty $penalty): RedirectResponse
    {
        $this->penalties->unlock(Auth::user(), $penalty);

        return back()->with('status', 'penalty-unlocked');
    }

    public function destroy(Request $request, LeaderboardPenalty $penalty): RedirectResponse
    {
        $data = $request->validate(['revoke_reason' => ['required', 'string', 'max:500']], [], ['revoke_reason' => 'lý do hủy']);
        $this->penalties->revoke(Auth::user(), $penalty, $data['revoke_reason']);

        return back()->with('status', 'penalty-revoked');
    }
}
