<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
     * access.checkout.store (25/8) — SỬA: nút "Đặt đơn" giờ tạo Order thật (trước đây chưa
     * submit gì cả, xem ghi chú cũ ở AccessService::placeOrder()). Lỗi validate (vd chọn "Dùng
     * để dạy" mà chưa được duyệt) quay lại đúng trang checkout để sửa, không văng ra trang lỗi.
     */
    public function store(Request $request, int $product): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validate([
            'scope' => ['required', 'string', 'in:personal_learning,teacher_teaching'],
            'include_print' => ['nullable', 'boolean'],
        ]);

        try {
            $order = $this->accessService->placeOrder($user, $product, $data);
        } catch (ValidationException $e) {
            return redirect()->route('access.checkout', $product)->withErrors($e->errors());
        }

        return redirect()->route('access.checkout', $product)
            ->with('status', 'order-placed')
            ->with('orderNo', $order->order_no);
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

    /**
     * access.activate.store (25/8) — SỬA: form "Kích hoạt" giờ submit thật (trước đây chưa nối
     * gì cả, xem ghi chú cũ ở AccessService::activateCode()). Lỗi (mã không tồn tại/sai
     * phạm vi/đã dùng) quay lại đúng trang kích hoạt, giữ nguyên mã đã gõ qua old('code').
     */
    public function activateStore(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validate(['code' => ['required', 'string', 'max:60']]);

        try {
            $this->accessService->activateCode($user, $data['code']);
        } catch (ValidationException $e) {
            return redirect()->route('access.activate')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('access.myAccess')->with('status', 'code-activated');
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
