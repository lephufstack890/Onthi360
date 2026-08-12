<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ActivationCodeService;
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
}
