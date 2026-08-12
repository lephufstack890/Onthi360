<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    private function statusLabel(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::Created, OrderStatus::PendingPayment, OrderStatus::PendingApproval => ['Chờ duyệt', 'warning'],
            OrderStatus::Approved, OrderStatus::Completed => ['Hoàn tất', 'success'],
            OrderStatus::Rejected, OrderStatus::Canceled, OrderStatus::Refunded => ['Từ chối/hủy', 'danger'],
        };
    }

    /** admin.orders.index (ADM-04) — 7.4: tạo đơn ≠ đã thanh toán ≠ đã có quyền. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');

        $counts = [
            'all' => Order::count(),
            'pending' => Order::whereIn('status', [OrderStatus::Created, OrderStatus::PendingPayment, OrderStatus::PendingApproval])->count(),
            'done' => Order::whereIn('status', [OrderStatus::Approved, OrderStatus::Completed])->count(),
            'rejected' => Order::whereIn('status', [OrderStatus::Rejected, OrderStatus::Canceled, OrderStatus::Refunded])->count(),
        ];

        $tabs = [
            ['label' => 'Tất cả', 'href' => route('admin.orders.index'), 'active' => $tab === 'all', 'count' => $counts['all']],
            ['label' => 'Chờ duyệt', 'href' => route('admin.orders.index', ['tab' => 'pending']), 'active' => $tab === 'pending', 'count' => $counts['pending']],
            ['label' => 'Hoàn tất', 'href' => route('admin.orders.index', ['tab' => 'done']), 'active' => $tab === 'done', 'count' => $counts['done']],
            ['label' => 'Từ chối/hủy', 'href' => route('admin.orders.index', ['tab' => 'rejected']), 'active' => $tab === 'rejected', 'count' => $counts['rejected']],
        ];

        $query = Order::with('buyer', 'items.product');
        match ($tab) {
            'pending' => $query->whereIn('status', [OrderStatus::Created, OrderStatus::PendingPayment, OrderStatus::PendingApproval]),
            'done' => $query->whereIn('status', [OrderStatus::Approved, OrderStatus::Completed]),
            'rejected' => $query->whereIn('status', [OrderStatus::Rejected, OrderStatus::Canceled, OrderStatus::Refunded]),
            default => null,
        };

        $total = (clone $query)->count();
        $orders = $query->latest()->limit(50)->get()->map(function ($o) {
            [$label, $tone] = $this->statusLabel($o->status);
            $itemsLabel = $o->items->map(fn ($it) => $it->product->title ?? '')->implode(', ');

            return [
                'id' => $o->id,
                'buyer' => $o->buyer->name ?? '',
                'items' => $itemsLabel,
                'total' => number_format($o->total_amount).'đ',
                'status' => $label,
                'tone' => $tone,
            ];
        })->all();

        return view('admin.orders.index', ['tab' => $tab, 'tabs' => $tabs, 'orders' => $orders, 'total' => $total]);
    }

    /** admin.orders.show — luồng Đặt đơn → Thanh toán → Duyệt → Cấp mã → Kích hoạt (7.4). */
    public function show(Request $request, int $order): View
    {
        $orderModel = Order::with('buyer', 'items.product')->findOrFail($order);

        return view('admin.orders.show', ['orderModel' => $orderModel]);
    }
}
