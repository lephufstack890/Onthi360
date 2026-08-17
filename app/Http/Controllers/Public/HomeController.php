<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\HomeService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly HomeService $homeService) {}

    public function index(): View
    {
        return view('welcome', $this->homeService->indexData());
    }
}
