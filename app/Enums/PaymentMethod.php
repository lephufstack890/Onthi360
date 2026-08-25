<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Offline = 'offline';
    case Vnpay = 'vnpay';

    // SỬA 25/8 (2) — "phải trừ token": thanh toán bằng số dư ví (App\Services\WalletService)
    // trừ ngay + cấp quyền tức thì, không chờ admin duyệt như Offline. Xem
    // App\Services\Access\AccessService::placeOrder() / App\Services\OrderActivationService::completeInstantly().
    case Token = 'token';
}
