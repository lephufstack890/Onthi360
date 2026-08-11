<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Offline = 'offline';
    case Vnpay = 'vnpay';
}
