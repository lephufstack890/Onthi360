<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CompetitionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetitionController extends Controller
{
    public function __construct(private CompetitionService $competitionService) {}

    /** admin.competitions.index (ADM-05) — 11.1: vòng đời cuộc thi. */
    public function index(Request $request): View
    {
        return view('admin.competitions.index', $this->competitionService->indexData());
    }
}
