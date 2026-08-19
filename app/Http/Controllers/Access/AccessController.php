<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccessController extends Controller
{
    public function __construct(private AccessService $accessService) {}

    /** access.checkout (ACC-03) — 7.4/7.5: checkout theo scope, sách mềm bắt buộc. */
    public function checkout(Request $request, int $product): View
    {
        $user = Auth::user();

        return view('access.checkout', $this->accessService->checkoutData($user, $product));
    }

    /**
     * access.activate (ACC-02).
     */
    public function activate(Request $request): View
    {
        $user = Auth::user();
        $code = $request->query('code');

        return view('access.activate', $this->accessService->activationLookup($user, $code));
    }

    /** access.myAccess (ACC-07) — 7.3: Đang có quyền / Sắp hết hạn / Đã hết hạn. */
    public function myAccess(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'active');

        return view('access.my-access', $this->accessService->myAccessData($user, $tab));
    }

    public function blocked(Request $request, int $material): View
    {
        $user = Auth::user();
        $classRoomId = $request->query('class') ? (int) $request->query('class') : null;

        return view('access.blocked', $this->accessService->blockedGates($user, $material, $classRoomId));
    }
}
