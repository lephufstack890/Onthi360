<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;

/**
 * Gom truy vấn/nhãn cho admin.orders.* (ADM-04, 7.4: tạo đơn ≠ đã thanh toán ≠ đã có quyền).
 *
 * App\Services\OrderActivationService (approveOfflineOrder/rejectOrder) vẫn CHƯA được nối ở
 * đây: OrderController chỉ có index/show, không có action approve/reject nào để gắn service
 * đó vào — thêm route/method mới là việc khác, ngoài phạm vi refactor tầng Service này.
 */
class OrderService
{
    private const PENDING_STATUSES = [OrderStatus::Created, OrderStatus::PendingPayment, OrderStatus::PendingApproval];

    private const DONE_STATUSES = [OrderStatus::Approved, OrderStatus::Completed];

    private const REJECTED_STATUSES = [OrderStatus::Rejected, OrderStatus::Canceled, OrderStatus::Refunded];

    public function __construct(private OrderRepositoryInterface $orders) {}

    private function statusLabel(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::Created, OrderStatus::PendingPayment, OrderStatus::PendingApproval => ['Chờ duyệt', 'warning'],
            OrderStatus::Approved, OrderStatus::Completed => ['Hoàn tất', 'success'],
            OrderStatus::Rejected, OrderStatus::Canceled, OrderStatus::Refunded => ['Từ chối/hủy', 'danger'],
        };
    }

    /** @return array{tab: string, tabs: array, orders: array, total: int} */
    public function indexData(string $tab): array
    {
        $counts = [
            'all' => $this->orders->count(),
            'pending' => $this->orders->countByStatuses(self::PENDING_STATUSES),
            'done' => $this->orders->countByStatuses(self::DONE_STATUSES),
            'rejected' => $this->orders->countByStatuses(self::REJECTED_STATUSES),
        ];

        $tabs = [
            ['label' => 'Tất cả', 'href' => route('admin.orders.index'), 'active' => $tab === 'all', 'count' => $counts['all']],
            ['label' => 'Chờ duyệt', 'href' => route('admin.orders.index', ['tab' => 'pending']), 'active' => $tab === 'pending', 'count' => $counts['pending']],
            ['label' => 'Hoàn tất', 'href' => route('admin.orders.index', ['tab' => 'done']), 'active' => $tab === 'done', 'count' => $counts['done']],
            ['label' => 'Từ chối/hủy', 'href' => route('admin.orders.index', ['tab' => 'rejected']), 'active' => $tab === 'rejected', 'count' => $counts['rejected']],
        ];

        $statuses = match ($tab) {
            'pending' => self::PENDING_STATUSES,
            'done' => self::DONE_STATUSES,
            'rejected' => self::REJECTED_STATUSES,
            default => null,
        };

        $total = $statuses === null ? $counts['all'] : $counts[$tab];
        $orders = $this->orders->filteredWithBuyerAndItems($statuses, 50)->map(function (Order $o) {
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

        return ['tab' => $tab, 'tabs' => $tabs, 'orders' => $orders, 'total' => $total];
    }

    /** @return array{orderModel: Order} */
    public function showData(int $orderId): array
    {
        $order = $this->orders->withBuyerAndItems($orderId);
        if ($order === null) {
            abort(404);
        }

        return ['orderModel' => $order];
    }
}
