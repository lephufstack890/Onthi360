<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\RankingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function __construct(private RankingService $rankingService) {}

    public function index(Request $request): View
    {
        // SỬA 12/11 — viên số trên tab "Tài khoản vi phạm".
        return view('admin.ranking.index', $this->rankingService->indexData() + [
            'violationCount' => app(\App\Services\Admin\LeaderboardPenaltyService::class)->lockedCount(),
        ]);
    }

    public function show(string $scope, int $id): View
    {
        return view('admin.ranking.show', $this->rankingService->showBoard($scope, $id));
    }
}
