<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\HomeService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly HomeService $homeService) {}

    /** home (PUB-01/02, 12.1) — trang chủ công khai, dữ liệu thật. */
    public function index(): View
    {
        return view('welcome', $this->homeService->indexData());
    }
}
