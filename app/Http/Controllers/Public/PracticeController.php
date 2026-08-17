<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\PracticeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PracticeController extends Controller
{
    public function __construct(private PracticeService $practiceService) {}

    public function index(Request $request): View
    {
        return view('public.practice.index', $this->practiceService->indexData(Auth::user()));
    }
}
