<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\MaterialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function __construct(private MaterialService $materialService) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'sach');

        return view('public.materials.index', $this->materialService->indexData($tab, $request->user()));
    }

    public function show(Request $request, int $material): View
    {
        return view('public.materials.show', $this->materialService->showData($material, $request->user()));
    }
}
