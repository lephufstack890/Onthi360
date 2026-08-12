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
}
