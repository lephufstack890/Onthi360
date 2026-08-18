<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function __construct(private LeaderboardService $leaderboardService) {}

    public function index(Request $request): View
    {
        $competitionId = $request->query('competition') !== null ? (int) $request->query('competition') : null;
        $examId = $request->query('exam') !== null ? (int) $request->query('exam') : null;

        return view(
            'public.leaderboard.index',
            $this->leaderboardService->indexData($competitionId, $examId, $request->user()),
        );
    }
}
