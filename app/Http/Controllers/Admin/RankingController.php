<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\RankingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function __construct(private RankingService $rankingService) {}

    /** admin.ranking.index — 11.2: không trộn phạm vi, không lộ rank tạm khi "Chờ công bố". */
    public function index(Request $request): View
    {
        return view('admin.ranking.index', $this->rankingService->indexData());
    }

    /** admin.ranking.show — chi tiết 1 bảng xếp hạng theo phạm vi cụ thể (11.2). */
    public function show(string $scope, int $id): View
    {
        return view('admin.ranking.show', $this->rankingService->showBoard($scope, $id));
    }
}
