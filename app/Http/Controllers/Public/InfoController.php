<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\InfoService;
use Illuminate\View\View;

class InfoController extends Controller
{
    public function __construct(private readonly InfoService $infoService) {}

    public function index(): View
    {
        return view('public.info.index', $this->infoService->indexData());
    }

    public function policy(string $slug): View
    {
        $data = $this->infoService->policyDetail($slug);

        abort_if($data === null, 404);

        return view('public.info.policy-show', $data);
    }
}
