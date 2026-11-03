<?php

namespace App\Notifications;

use App\Models\TokenTopup;
use App\Support\TokenPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * SỬA 3/11 — gửi cho quản trị viên (admin / super admin / biên tập viên có quyền duyệt đơn) khi người nạp bấm
 * "Tôi đã chuyển khoản" (App\Services\WalletService::confirmTransfer). Kênh 'database' → hiện ở chuông thông báo.
 */
class TokenTopupTransferConfirmed extends Notification
{
    use Queueable;

    public function __construct(private readonly TokenTopup $topup) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $name = $this->topup->user->name ?? 'Người dùng';

        return [
            'icon' => '💳',
            'tone' => 'warning',
            'title' => $name.' đã chuyển khoản nạp '.TokenPrice::vnd($this->topup->amount).' — chờ duyệt',
            'text' => 'Mã CK '.$this->topup->transfer_code.' · '.TokenPrice::label($this->topup->amount).'. Đối soát sao kê rồi bấm Duyệt để cộng token.',
            'url' => route('admin.orders.index'),
        ];
    }
}
