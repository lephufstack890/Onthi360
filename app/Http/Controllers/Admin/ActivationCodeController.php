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

    /** admin.activation-codes.index — 7.4: mã sai scope không tự chuyển đổi. */
    public function index(Request $request): View
    {
        return view('admin.activation-codes.index', $this->activationCodeService->indexData());
    }

    /** admin.activation-codes.revoke — PHẢI có lý do + audit log (10.4). */
    public function revoke(Request $request, ActivationCode $activationCode): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->activationCodeService->revoke($activationCode, $data['reason']);

        return redirect()->route('admin.activation-codes.index')->with('status', 'code-revoked');
    }
}
