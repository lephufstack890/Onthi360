<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    /** admin.orders.index (ADM-04) — 7.4: tạo đơn ≠ đã thanh toán ≠ đã có quyền. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');

        return view('admin.orders.index', $this->orderService->indexData($tab));
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
}
