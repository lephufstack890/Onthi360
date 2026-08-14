<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TokenTopup;
use App\Services\Admin\OrderService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private WalletService $walletService,
    ) {}

    /**
     * admin.orders.index (ADM-04) — 7.4: tạo đơn ≠ đã thanh toán ≠ đã có quyền. Gộp thêm
     * danh sách "Yêu cầu nạp token" (note họp 13/8, mục 7-8) ở cùng trang thay vì thêm mục
     * điều hướng riêng — cùng bản chất "đối soát thanh toán thủ công rồi mới duyệt" với đơn.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');

        $data = $this->orderService->indexData($tab);
        $data['tokenTopups'] = $this->walletService->pendingAndRecentForAdmin(20);

        return view('admin.orders.index', $data);
    }

    /** admin.orders.show — luồng Đặt đơn → Thanh toán → Duyệt → Cấp mã → Kích hoạt (7.4). */
    public function show(Request $request, int $order): View
    {
        return view('admin.orders.show', $this->orderService->showData($order));
    }

    /** admin.orders.approve — duyệt đơn thanh toán ngoài hệ thống, tự sinh mã kích hoạt (7.4). */
    public function approve(Request $request, Order $order): RedirectResponse
    {
        $this->orderService->approve(Auth::user(), $order);

        return redirect()->route('admin.orders.show', $order->id)->with('status', 'order-approved');
    }

    /** admin.orders.reject — PHẢI có lý do + audit log (7.4, 10.4). */
    public function reject(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->orderService->reject(Auth::user(), $order, $data['reason']);

        return redirect()->route('admin.orders.show', $order->id)->with('status', 'order-rejected');
    }

    /** admin.orders.token-topups.approve — đối soát sao kê ngân hàng xong mới cộng token (note họp 13/8, mục 7-8). */
    public function approveTopup(Request $request, TokenTopup $tokenTopup): RedirectResponse
    {
        try {
            $this->walletService->approveTopup(Auth::user(), $tokenTopup);
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.index')->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.index')->with('status', 'topup-approved');
    }

    /** admin.orders.token-topups.reject — PHẢI có lý do (vd không thấy tiền về đúng mã, sai số tiền). */
    public function rejectTopup(Request $request, TokenTopup $tokenTopup): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->walletService->rejectTopup(Auth::user(), $tokenTopup, $data['reason']);
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.index')->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.index')->with('status', 'topup-rejected');
    }
}
