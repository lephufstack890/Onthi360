<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\CompetitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CompetitionController extends Controller
{
    public function __construct(private CompetitionService $competitionService) {}

    public function index(Request $request): View
    {
        return view('public.competitions.index', $this->competitionService->indexData());
    }

    public function show(Request $request, int $competition): View
    {
        return view('public.competitions.show', $this->competitionService->showData($competition, Auth::user()));
    }
}
