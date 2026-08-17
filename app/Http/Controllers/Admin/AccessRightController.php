<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessRight;
use App\Services\Admin\AccessRightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccessRightController extends Controller
{
    public function __construct(private AccessRightService $accessRightService) {}

    public function index(Request $request): View
    {
        return view('admin.access-rights.index', $this->accessRightService->indexData());
    }

    public function create(): View
    {
        return view('admin.access-rights.create', $this->accessRightService->createFormData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'scope' => ['required', 'string', 'in:personal_learning,teacher_teaching'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $accessRight = $this->accessRightService->grant(Auth::user(), $data, $data['reason']);

        return redirect()->route('admin.access-rights.show', $accessRight->id)->with('status', 'access-granted');
    }

    public function show(int $accessRight): View
    {
        return view('admin.access-rights.show', $this->accessRightService->showData($accessRight));
    }

    public function revoke(Request $request, AccessRight $accessRight): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->accessRightService->revoke($accessRight, $data['reason']);

        return redirect()->route('admin.access-rights.show', $accessRight->id)->with('status', 'access-revoked');
    }
}
