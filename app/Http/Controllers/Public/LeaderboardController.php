<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function __construct(private LeaderboardService $leaderboardService) {}

    /** leaderboard.index (PUB-09, 11.2) — ?competition= chọn bảng cụ thể, mặc định bảng mới nhất. */
    public function index(Request $request): View
    {
        $competitionId = $request->query('competition') !== null ? (int) $request->query('competition') : null;

        return view(
            'public.leaderboard.index',
            $this->leaderboardService->indexData($competitionId, $request->user()),
        );
    }
}
