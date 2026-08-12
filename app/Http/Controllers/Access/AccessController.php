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
     *
     * Route này chỉ có GET và form trong Blade chưa có submit thật (không có
     * name/method) — không tự thêm route/handler POST mới ở đây (ngoài phạm
     * vi refactor). Nếu URL có ?code=..., tra mã thật và tính sẵn
     * AccessDecision qua App\Services\OrderActivationService::canActivate()
     * để trang có dữ liệu thật hiển thị lý do mã dùng được/không; khi submit
     * thật được xây, handler đó gọi tiếp OrderActivationService::activate().
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

    /**
     * access.blocked (ACC-08) — 7.3: 3 cửa Thành viên/lớp, Quyền cá nhân, Tiến độ chung,
     * tính thật qua App\Services\AccessGateService::canAccessMaterial().
     * ?class=<id> tùy chọn: ngữ cảnh lớp khi bài bị khóa trong lộ trình lớp (route hiện
     * không có {class}, không tự thêm param bắt buộc mới).
     */
    public function blocked(Request $request, int $material): View
    {
        $user = Auth::user();
        $classRoomId = $request->query('class') ? (int) $request->query('class') : null;

        return view('access.blocked', $this->accessService->blockedGates($user, $material, $classRoomId));
    }
}
