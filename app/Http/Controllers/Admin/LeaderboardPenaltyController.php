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

    public function index(Request $request): View
    {
        return view('admin.ranking.violations', $this->penalties->indexData($request->query('filter')) + [
            'reasons' => LeaderboardPenalty::REASONS,
            'lockDays' => LeaderboardPenalty::LOCK_DAYS,
        ]);
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
        ], [], [
            'account' => 'tài khoản', 'reason' => 'lý do', 'lock_days' => 'số ngày khóa',
            'points_deducted' => 'điểm trừ', 'tokens_deducted' => 'token trừ',
        ]);

        $penalty = $this->penalties->store(Auth::user(), $data);

        return redirect()->route('admin.ranking.violations.index')
            ->with('status', 'penalty-created')
            ->with('penaltyTokens', $penalty->tokens_deducted);
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
