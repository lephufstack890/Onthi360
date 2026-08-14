<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\CompetitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** competitions.* (PUB-08, 11.1 "Menu Cuộc thi"). */
class CompetitionController extends Controller
{
    public function __construct(private CompetitionService $competitionService) {}

    /** competitions.index — danh sách cuộc thi/khảo sát công khai. */
    public function index(Request $request): View
    {
        return view('public.competitions.index', $this->competitionService->indexData());
    }

    /** competitions.show — chi tiết cuộc thi: thời gian, thể lệ, quy tắc, CTA theo trạng thái. */
    public function show(Request $request, int $competition): View
    {
        return view('public.competitions.show', $this->competitionService->showData($competition, Auth::user()));
    }
}
