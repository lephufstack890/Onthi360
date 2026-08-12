<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\OrderService;
use Illuminate\Http\Request;
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
}
