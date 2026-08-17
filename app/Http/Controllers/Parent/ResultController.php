<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ChildService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function __construct(private ChildService $childService) {}

    public function index(Request $request): View|RedirectResponse
    {
        $children = $this->childService->verifiedChildrenForParent($request->user());

        if (count($children) === 1) {
            return redirect()->route('parent.children.show', ['child' => $children[0]['id'], 'tab' => 'results']);
        }

        return view('parent.results.index', ['children' => $children]);
    }
}
