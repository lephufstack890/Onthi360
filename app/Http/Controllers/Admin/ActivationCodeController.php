<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Services\Admin\ActivationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivationCodeController extends Controller
{
    public function __construct(private ActivationCodeService $activationCodeService) {}

    public function index(Request $request): View
    {
        return view('admin.activation-codes.index', $this->activationCodeService->indexData());
    }

    public function revoke(Request $request, ActivationCode $activationCode): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->activationCodeService->revoke($activationCode, $data['reason']);

        return redirect()->route('admin.activation-codes.index')->with('status', 'code-revoked');
    }
}
