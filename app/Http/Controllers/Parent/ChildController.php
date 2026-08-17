<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ChildService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChildController extends Controller
{
    public function __construct(private ChildService $childService) {}

    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('parent.children.index', $this->childService->listForParent($user));
    }

    public function storeLinkRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $this->childService->requestLink($request->user(), $data['student_email']);
        } catch (ValidationException $e) {
            return redirect()->route('parent.children.index')->withErrors($e->errors());
        }

        return redirect()->route('parent.children.index')->with('status', 'link-requested');
    }

    public function show(Request $request, int $child): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'overview');

        return view('parent.children.show', $this->childService->showForParent($user, $child, $tab));
    }
}
